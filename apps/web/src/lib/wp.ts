import { mockProducts, type GameProduct } from "../data/mock";

const WP_API_URL = import.meta.env.PUBLIC_WP_API_URL;

interface WpProduct {
  id: number;
  title: { rendered: string };
  excerpt: { rendered: string };
  meta?: { price?: number; category?: string };
}

function stripHtml(html: string): string {
  return html.replace(/<[^>]+>/g, "").trim();
}

function mapWpProduct(product: WpProduct, index: number): GameProduct {
  const price = product.meta?.price;
  const categories: GameProduct["category"][] = ["pc", "mobile", "cards", "service"];

  return {
    id: String(product.id),
    title: stripHtml(product.title.rendered),
    category: (product.meta?.category as GameProduct["category"]) || categories[index % categories.length],
    categoryLabel: "Official Store",
    price: typeof price === "number" ? `฿${price.toLocaleString()}` : "฿299",
    unit: "แพ็กเกจมาตรฐาน",
    deliverySpeed: "ส่งออโต้ทันที",
    image: mockProducts[index % mockProducts.length]?.image || "",
    color: "from-blue-600/20 to-indigo-700/10",
  };
}

export async function getProducts(): Promise<GameProduct[]> {
  if (!WP_API_URL) return mockProducts;

  try {
    const res = await fetch(`${WP_API_URL}/wp/v2/product?_fields=id,title,excerpt,meta&orderby=id&order=asc`, {
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

