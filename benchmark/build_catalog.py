#!/usr/bin/env python3
"""Build a deterministic, API-import-friendly Shopware benchmark catalog."""
from __future__ import annotations

import argparse
import json
import random
import uuid
from pathlib import Path

ROOT = Path(__file__).resolve().parent
SOURCE = ROOT / "data/product-families.en.json"
DEFAULT_OUTPUT = ROOT / "data/catalog.generated.json"
NAMESPACE = uuid.UUID("826a55e4-b423-48cf-8aa8-34da05826046")
AXES = {
    "Outdoor & Travel": (("Color", ["Forest Green", "Slate Blue", "Sandstone", "Charcoal", "Rust Orange", "Glacier White", "Moss", "Burgundy"]), ("Size", ["Compact", "Small", "Medium", "Large", "20 L", "28 L", "35 L", "45 L"])),
    "Kitchen & Dining": (("Finish", ["Matte Black", "Brushed Steel", "Warm White", "Sage", "Terracotta", "Ocean Blue", "Natural Oak", "Stone Gray"]), ("Capacity", ["250 ml", "350 ml", "500 ml", "750 ml", "1 L", "1.5 L", "2 L", "Set of 4"])),
    "Home & Living": (("Color", ["Warm White", "Sage", "Terracotta", "Natural Oak", "Charcoal", "Sand", "Ocean Blue", "Rust"]), ("Size", ["Small", "Medium", "Large", "Extra Large", "30 cm", "45 cm", "60 cm", "Set of 2"])),
    "Fitness & Recovery": (("Resistance", ["Extra Light", "Light", "Medium", "Firm", "Extra Firm", "10 kg", "15 kg", "20 kg"]), ("Length", ["30 cm", "45 cm", "60 cm", "90 cm", "1 m", "1.5 m", "2 m", "Set of 3"])),
    "Office & Stationery": (("Color", ["Black", "Navy", "Forest", "Burgundy", "Stone", "Mustard", "Natural", "Red"]), ("Format", ["A6", "A5", "A4", "Pocket", "Slim", "Wide", "Single", "Set of 3"])),
    "Audio & Electronics": (("Color", ["Black", "White", "Graphite", "Navy", "Sage", "Silver", "Coral", "Midnight Blue"]), ("Configuration", ["Compact", "Standard", "Extended", "USB-C", "USB-A", "1 m", "2 m", "3 m"])),
    "Apparel & Accessories": (("Color", ["Forest Green", "Navy", "Charcoal", "Oatmeal", "Rust", "Burgundy", "Stone", "Black"]), ("Size", ["XS", "S", "M", "L", "XL", "XXL", "One Size", "Pack of 2"])),
    "Garden & Workshop": (("Finish", ["Galvanized", "Forest Green", "Black", "Terracotta", "Natural", "Cobalt Blue", "Stone Gray", "Rust Red"]), ("Size", ["Small", "Medium", "Large", "Compact", "Standard", "Wide", "Set of 2", "Set of 4"])),
}
# Expand each dimension to twelve realistic choices for a 5,000-row fixture.
AXIS_EXTENSION = {
    "Outdoor & Travel": (("Summit Red", "Deep Teal", "Lightweight"), ("15 L", "25 L", "40 L", "60 L")),
    "Kitchen & Dining": (("Cream", "Copper", "Deep Green"), ("200 ml", "600 ml", "900 ml", "2.5 L")),
    "Home & Living": (("Cream", "Deep Green", "Blue Gray"), ("20 cm", "75 cm", "90 cm", "Set of 3")),
    "Fitness & Recovery": (("Soft Gray", "Bright Blue", "Black"), ("20 cm", "75 cm", "1.2 m", "Set of 5")),
    "Office & Stationery": (("Teal", "Cream", "Olive"), ("B5", "Letter", "Dotted", "Set of 5")),
    "Audio & Electronics": (("Teal", "Cream", "Olive"), ("0.5 m", "1.5 m", "4 m", "Wireless")),
    "Apparel & Accessories": (("Cream", "Olive", "Teal"), ("XXS", "3XL", "Tall", "Petite")),
    "Garden & Workshop": (("Cream", "Olive", "Teal"), ("Extra Small", "Extra Large", "Set of 3", "Set of 5")),
}
for category, extension in AXIS_EXTENSION.items():
    first, second = AXES[category]
    AXES[category] = ((first[0], first[1] + list(extension[0])), (second[0], second[1] + list(extension[1])))


def stable_id(value: str) -> str:
    return uuid.uuid5(NAMESPACE, value).hex


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--count", type=int, default=5000, help="total parent plus variant products (default: 5000)")
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT)
    parser.add_argument("--seed", type=int, default=20261124)
    args = parser.parse_args()
    if args.count < 1:
        parser.error("--count must be positive")
    source = json.loads(SOURCE.read_text())
    families = source["families"]
    if args.count < len(families):
        parser.error(f"--count must be at least {len(families)} to include every family")

    rng = random.Random(args.seed)
    products = []
    for family in families:
        key = family["familyKey"]
        parent_sku = f"BEN-{key.upper()}-P"
        category_axes = AXES[family["category"]]
        products.append({
            "id": stable_id(parent_sku), "productNumber": parent_sku,
            "familyKey": key, "parent": True, "name": family["name"],
            "description": family["description"], "category": family["category"],
            "properties": (family["properties"] + [{"group": "Design", "value": "Everyday utility"}] * (4 - len(family["properties"])))[:4], "variantAxes": [
                {"name": axis_name, "values": values} for axis_name, values in category_axes
            ],
            "priceEur": round(rng.uniform(18, 320), 2), "stock": rng.randint(0, 250),
        })

    # Round-robin across families so every family receives a similar number of variants.
    per_family = [[] for _ in families]
    for index, family in enumerate(families):
        axes = AXES[family["category"]]
        for a, value_a in enumerate(axes[0][1]):
            for b, value_b in enumerate(axes[1][1]):
                per_family[index].append((a, b, value_a, value_b))
    child_count = args.count - len(families)
    cursors = [0] * len(families)
    while child_count:
        progressed = False
        for i, family in enumerate(families):
            if child_count == 0:
                break
            variants = per_family[i]
            if cursors[i] >= len(variants):
                continue
            a, b, value_a, value_b = variants[cursors[i]]
            cursors[i] += 1
            parent_sku = f"BEN-{family['familyKey'].upper()}-P"
            sku = f"BEN-{family['familyKey'].upper()}-{a + 1:02d}-{b + 1:02d}"
            base_price = 18 + ((i * 37 + a * 23 + b * 11) % 304)
            products.append({
                "id": stable_id(sku), "productNumber": sku,
                "familyKey": family["familyKey"], "parentId": stable_id(parent_sku),
                "parent": False, "name": f"{family['name']} — {value_a} / {value_b}",
                "category": family["category"],
                "options": [{"group": AXES[family['category']][0][0], "value": value_a}, {"group": AXES[family['category']][1][0], "value": value_b}],
                "priceEur": round(float(base_price), 2), "stock": (i * 31 + a * 17 + b * 13) % 251,
            })
            child_count -= 1
            progressed = True
        if not progressed and child_count:
            parser.error(f"requested count exceeds variant capacity ({len(families) * 64 + len(families)})")

    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps({
        "schemaVersion": 1, "language": "en", "seed": args.seed,
        "productCount": len(products), "familyCount": len(families),
        "families": [{"familyKey": f["familyKey"], "name": f["name"], "category": f["category"]} for f in families],
        "products": products,
    }, ensure_ascii=False, separators=(",", ":")) + "\n")
    print(f"Wrote {len(products)} products across {len(families)} families to {args.output}")


if __name__ == "__main__":
    main()
