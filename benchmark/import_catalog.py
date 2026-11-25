#!/usr/bin/env python3
"""Import the generated benchmark catalog and dynamic price-listing fixture via Admin API."""
from __future__ import annotations

import argparse
import json
import os
import time
import urllib.error
import urllib.request
import uuid
from collections import defaultdict
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parent
DEFAULT_CATALOG = ROOT / "data/catalog.generated.json"
NAMESPACE = uuid.UUID("53c41b2e-8b20-42bf-a1d4-9dc8632a1224")


def stable_id(key: str) -> str:
    return uuid.uuid5(NAMESPACE, key).hex


class ShopwareApi:
    def __init__(self, base_url: str, username: str, password: str) -> None:
        self.base_url = base_url.rstrip("/")
        self.token = self._request("/api/oauth/token", {
            "grant_type": "password", "client_id": "administration", "scopes": "write",
            "username": username, "password": password,
        }, authenticated=False).get("access_token")
        if not self.token:
            raise RuntimeError("Admin API did not return an access token")

    def _request(self, path: str, payload: dict[str, Any], authenticated: bool = True) -> dict[str, Any]:
        headers = {"Content-Type": "application/json", "Accept": "application/json"}
        if authenticated:
            headers["Authorization"] = f"Bearer {self.token}"
        request = urllib.request.Request(
            self.base_url + path, json.dumps(payload, separators=(",", ":")).encode(),
            headers=headers, method="POST",
        )
        try:
            with urllib.request.urlopen(request, timeout=300) as response:
                body = response.read()
        except (urllib.error.HTTPError, urllib.error.URLError) as error:
            body = getattr(error, "read", lambda: b"")().decode(errors="replace")
            raise RuntimeError(f"{path} failed: {error}: {body[:1500]}") from error
        result = json.loads(body)
        if "errors" in result:
            raise RuntimeError(f"{path} returned API errors: {json.dumps(result['errors'])[:1500]}")
        return result

    def search_one(self, entity: str, field: str, value: str) -> dict[str, Any]:
        result = self._request(f"/api/search/{entity}", {
            "page": 1, "limit": 5,
            "filter": [{"type": "equals", "field": field, "value": value}],
        })
        records = result.get("data", [])
        if len(records) != 1:
            raise RuntimeError(f"Expected one {entity} where {field}={value!r}, found {len(records)}")
        return records[0]

    def sync(self, key: str, entity: str, payload: list[dict[str, Any]]) -> None:
        if not payload:
            return
        self._request("/api/_action/sync", {
            key: {"entity": entity, "action": "upsert", "payload": payload},
        })


def build_property_groups(families: list[dict[str, Any]]) -> tuple[list[dict[str, Any]], dict[tuple[str, str, str], str]]:
    groups: dict[tuple[str, str], set[str]] = defaultdict(set)
    for family in families:
        for axis in family.get("variantAxes", []):
            groups[(family["category"], axis["name"])].update(axis["values"])
    for family in families:
        for prop in family.get("properties", []):
            groups[(family["category"], prop["group"])].add(prop["value"])

    entities = []
    option_ids: dict[tuple[str, str, str], str] = {}
    for (category, name), values in sorted(groups.items()):
        group_key = f"{category}|{name}"
        options = []
        for position, value in enumerate(sorted(values), start=1):
            option_id = stable_id(f"option|{group_key}|{value}")
            option_ids[(category, name, value)] = option_id
            options.append({"id": option_id, "name": value, "position": position})
        entities.append({
            "id": stable_id(f"group|{group_key}"), "name": name,
            "displayType": "text", "sortingType": "alphanumeric",
            "filterable": True, "options": options,
        })
    return entities, option_ids


def product_payload(
    product: dict[str, Any], family_by_key: dict[str, dict[str, Any]], option_ids: dict[tuple[str, str, str], str],
    channel_id: str, currency_id: str, tax_id: str,
) -> dict[str, Any]:
    price = float(product["priceEur"])
    entity: dict[str, Any] = {
        "id": product["id"], "productNumber": product["productNumber"],
        "name": product["name"], "active": True, "stock": product["stock"],
        "isCloseout": False, "taxId": tax_id,
        "price": [{"currencyId": currency_id, "gross": price, "net": round(price / 1.19, 2), "linked": True}],
        "visibilities": [{"salesChannelId": channel_id, "visibility": 30}],
    }
    if product.get("parent"):
        family = family_by_key[product["familyKey"]]
        entity["description"] = family["description"]
        entity["properties"] = [
            {"id": option_ids[(family["category"], prop["group"], prop["value"])]}
            for prop in family.get("properties", [])
        ]
        settings = []
        for axis in product.get("variantAxes", []):
            for position, value in enumerate(axis["values"], start=len(settings) + 1):
                option_id = option_ids[(family["category"], axis["name"], value)]
                settings.append({"id": stable_id(f"setting|{product['id']}|{option_id}"), "optionId": option_id, "position": position})
        entity["configuratorSettings"] = settings
    else:
        entity["parentId"] = product["parentId"]
        entity["options"] = [
            {"id": option_ids[(product["category"], option["group"], option["value"])]}
            for option in product["options"]
        ]
    return entity


def chunks(values: list[dict[str, Any]], size: int):
    for start in range(0, len(values), size):
        yield start, values[start:start + size]


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--catalog", type=Path, default=DEFAULT_CATALOG)
    parser.add_argument("--base-url", default=os.getenv("SHOP_URL", "http://localhost:8000"))
    parser.add_argument("--sales-channel", default="PostgreSQL Benchmark")
    parser.add_argument("--username", default="admin")
    parser.add_argument("--password", default=os.getenv("SHOPWARE_ADMIN_PASSWORD", "shopware"))
    parser.add_argument("--batch-size", type=int, default=100)
    parser.add_argument("--category-name", default="Benchmark Dynamic Price Listing")
    parser.add_argument("--min-price", type=float, default=50)
    parser.add_argument("--max-price", type=float, default=250)
    parser.add_argument("--metadata-file", type=Path, help="Write generated category and Store API credentials here")
    args = parser.parse_args()
    if args.batch_size < 1:
        parser.error("--batch-size must be positive")

    catalog = json.loads(args.catalog.read_text())
    products = catalog["products"]
    families = json.loads((ROOT / "data/product-families.en.json").read_text())["families"]
    family_by_key = {family["familyKey"]: family for family in families}
    parents = [product for product in products if product.get("parent")]
    variants = [product for product in products if not product.get("parent")]
    if len(parents) != len(families):
        parser.error("catalog must contain exactly one parent per family")
    parent_by_family = {product["familyKey"]: product for product in parents}
    for family in families:
        family["variantAxes"] = parent_by_family[family["familyKey"]]["variantAxes"]

    api = ShopwareApi(args.base_url, args.username, args.password)
    sales_channels = api._request("/api/search/sales-channel", {"page": 1, "limit": 100}).get("data", [])
    matching_channels = [channel for channel in sales_channels if channel.get("translated", {}).get("name") == args.sales_channel]
    if len(matching_channels) != 1:
        raise RuntimeError(f"Expected one sales channel named {args.sales_channel!r}, found {len(matching_channels)}")
    channel = matching_channels[0]
    currency = api.search_one("currency", "isoCode", "EUR")
    tax = api.search_one("tax", "name", "Standard rate")
    channel_id = channel["id"]
    currency_id = currency["id"]
    tax_id = tax["id"]

    start = time.monotonic()
    groups, option_ids = build_property_groups(families)
    api.sync("benchmark-property-groups", "property_group", groups)
    group_duration = time.monotonic() - start

    stream_id = stable_id("benchmark-price-stream")
    api.sync("benchmark-price-stream", "product_stream", [{
        "id": stream_id, "name": "Benchmark products EUR 50 to 250",
        "filters": [{
            "id": stable_id("benchmark-price-range"), "type": "range", "field": "product.price",
            "parameters": {"gte": args.min_price, "lte": args.max_price}, "position": 0,
        }],
    }])
    category_id = stable_id("benchmark-dynamic-price-category")
    api.sync("benchmark-dynamic-category", "category", [{
        "id": category_id, "parentId": channel["navigationCategoryId"],
        "name": args.category_name, "active": True, "visible": True,
        "visibleInNavigation": True, "productAssignmentType": "product_stream",
        "productStreamId": stream_id, "displayNestedProducts": True,
    }])

    products_start = time.monotonic()
    def convert(batch: list[dict[str, Any]]) -> list[dict[str, Any]]:
        return [product_payload(product, family_by_key, option_ids, channel_id, currency_id, tax_id) for product in batch]

    api.sync("benchmark-parent-products", "product", convert(parents))
    for offset, batch in chunks(variants, args.batch_size):
        api.sync(f"benchmark-variants-{offset:05d}", "product", convert(batch))
        completed = min(offset + len(batch), len(variants))
        print(f"Imported {completed}/{len(variants)} variants", flush=True)
    products_duration = time.monotonic() - products_start

    summary = {
        "productCount": len(products), "parentCount": len(parents), "variantCount": len(variants),
        "familyCount": len(families), "propertyGroupCount": len(groups),
        "categoryId": category_id, "salesChannelId": channel_id,
        "priceRangeEur": [args.min_price, args.max_price],
        "propertySetupSeconds": round(group_duration, 3), "productImportSeconds": round(products_duration, 3),
        "totalImportSeconds": round(time.monotonic() - start, 3),
    }
    if args.metadata_file:
        args.metadata_file.write_text(json.dumps({**summary, "salesChannelAccessKey": channel["accessKey"]}, indent=2) + "\n")
    print(json.dumps(summary))


if __name__ == "__main__":
    main()
