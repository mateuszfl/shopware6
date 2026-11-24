import http from 'k6/http';
import { check } from 'k6';
import { Counter, Trend } from 'k6/metrics';

const listingDuration = new Trend('dynamic_group_listing_duration', true);
const listingFailures = new Counter('dynamic_group_listing_failures');

const shopUrl = requiredEnv('SHOP_URL').replace(/\/$/, '');
const accessKey = requiredEnv('SALES_CHANNEL_ACCESS_KEY');
const categoryId = requiredEnv('BENCHMARK_CATEGORY_ID');

export const options = {
    scenarios: {
        dynamic_product_group_listing: {
            executor: 'constant-arrival-rate',
            rate: Number(__ENV.RATE_PER_SECOND || 10),
            timeUnit: '1s',
            duration: __ENV.DURATION || '5m',
            preAllocatedVUs: Number(__ENV.PREALLOCATED_VUS || 10),
            maxVUs: Number(__ENV.MAX_VUS || 50),
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        checks: ['rate>0.99'],
    },
};

export default function () {
    const response = http.post(
        `${shopUrl}/store-api/product-listing/${categoryId}`,
        JSON.stringify({ page: 1, limit: Number(__ENV.PAGE_SIZE || 24) }),
        {
            headers: {
                'Content-Type': 'application/json',
                'sw-access-key': accessKey,
            },
            tags: { scenario: 'dynamic-product-group-listing' },
        },
    );

    listingDuration.add(response.timings.duration);
    const ok = check(response, {
        'listing returns HTTP 200': (result) => result.status === 200,
        'listing contains products': (result) => {
            if (result.status !== 200) {
                return false;
            }

            const elements = result.json('elements');

            return Array.isArray(elements) ? elements.length > 0 : Boolean(elements && Object.keys(elements).length > 0);
        },
    });

    if (!ok) {
        listingFailures.add(1);
    }
}

function requiredEnv(name) {
    const value = __ENV[name];

    if (!value) {
        throw new Error(`Missing required environment variable: ${name}`);
    }

    return value;
}
