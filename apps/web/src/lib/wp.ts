import { mockProducts, type ProductTile } from "../data/mock";

const WP_API_URL = import.meta.env.PUBLIC_WP_API_URL;

// Falls back to mock data whenever the WordPress REST API isn't reachable yet
// (e.g. before `docker compose up` / before products are entered in wp-admin).
export async function getProducts(): Promise<ProductTile[]> {
  if (!WP_API_URL) return mockProducts;

  try {
    const res = await fetch(`${WP_API_URL}/wp/v2/product`, { signal: AbortSignal.timeout(2000) });
    if (!res.ok) return mockProducts;

    const data = await res.json();
    if (!Array.isArray(data) || data.length === 0) return mockProducts;

    return data as ProductTile[];
  } catch {
    return mockProducts;
  }
}
