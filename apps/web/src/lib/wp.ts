import { mockProducts, type ProductTile } from "../data/mock";

const WP_API_URL = import.meta.env.PUBLIC_WP_API_URL;

interface WpProduct {
  id: number;
  title: { rendered: string };
  excerpt: { rendered: string };
  meta?: { price?: number };
}

function stripHtml(html: string): string {
  return html.replace(/<[^>]+>/g, "").trim();
}

// Presentation-only concerns (accent color, tile size) aren't stored in WordPress —
// they're derived here from position, keeping WP focused on content/SEO.
function mapWpProduct(product: WpProduct, index: number): ProductTile {
  const price = product.meta?.price;

  return {
    id: String(product.id),
    title: stripHtml(product.title.rendered).toUpperCase(),
    subtitle: stripHtml(product.excerpt.rendered),
    price: typeof price === "number" ? `$${price.toFixed(2)}` : "",
    accent: index % 2 === 0 ? "green" : "purple",
    size: index === 0 ? "large" : index === 1 ? "medium" : "small",
  };
}

// Falls back to mock data whenever the WordPress REST API isn't reachable yet
// (e.g. before `docker compose up` / before products are entered in wp-admin).
export async function getProducts(): Promise<ProductTile[]> {
  if (!WP_API_URL) return mockProducts;

  try {
    const res = await fetch(`${WP_API_URL}/wp/v2/product?_fields=id,title,excerpt,meta`, {
      signal: AbortSignal.timeout(2000),
    });
    if (!res.ok) return mockProducts;

    const data = await res.json();
    if (!Array.isArray(data) || data.length === 0) return mockProducts;

    return data.map(mapWpProduct);
  } catch {
    return mockProducts;
  }
}
