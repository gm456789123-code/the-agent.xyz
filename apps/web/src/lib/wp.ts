import { mockProducts, type ProductDeal } from "../data/mock";

const WP_API_URL = import.meta.env.PUBLIC_WP_API_URL;

interface WpProduct {
  id: number;
  title: { rendered: string };
  excerpt: { rendered: string };
  meta?: { price?: number; category?: string; store_name?: string; unit?: string };
  featured_image_url?: string | null;
}

const CATEGORY_COLORS: Record<ProductDeal["category"], string> = {
  streaming: "from-rose-500/20 to-red-600/10",
  gaming: "from-cyan-500/20 to-sky-600/10",
  software: "from-purple-500/20 to-violet-600/10",
  services: "from-emerald-500/20 to-teal-700/10",
  vouchers: "from-blue-600/20 to-indigo-700/10",
};

const CATEGORY_LABELS: Record<ProductDeal["category"], string> = {
  streaming: "แอปสตรีมมิ่ง",
  gaming: "เกม PC",
  software: "ซอฟต์แวร์",
  services: "บริการออนไลน์",
  vouchers: "บัตรดิจิทัล",
};

function stripHtml(html: string): string {
  return html.replace(/<[^>]+>/g, "").trim();
}

function resolveCategory(raw?: string): ProductDeal["category"] {
  const valid: ProductDeal["category"][] = ["streaming", "gaming", "software", "services", "vouchers"];
  return valid.includes(raw as ProductDeal["category"]) ? (raw as ProductDeal["category"]) : "gaming";
}

function mapWpProduct(product: WpProduct, index: number): ProductDeal {
  const category = resolveCategory(product.meta?.category);
  const categoryLabel = CATEGORY_LABELS[category];
  const price = product.meta?.price;
  const fallbackImage = mockProducts[index % mockProducts.length]?.image || "";

  return {
    id: String(product.id),
    title: stripHtml(product.title.rendered),
    category,
    categoryLabel,
    storeName: product.meta?.store_name || "Nexus Arcade Official Store",
    storeRating: 4.8,
    price: typeof price === "number" ? `฿${price.toLocaleString()}` : "฿299",
    unit: product.meta?.unit || stripHtml(product.excerpt.rendered) || "แพ็กเกจมาตรฐาน",
    deliverySpeed: "ส่งออโต้ทันที",
    image: product.featured_image_url || fallbackImage,
    color: CATEGORY_COLORS[category],
  };
}

export async function getProducts(): Promise<ProductDeal[]> {
  if (!WP_API_URL) return mockProducts;

  try {
    const res = await fetch(
      `${WP_API_URL}/wp/v2/product?_fields=id,title,excerpt,meta,featured_image_url&orderby=id&order=desc&per_page=50`,
      { signal: AbortSignal.timeout(2000) }
    );
    if (!res.ok) return mockProducts;

    const data = await res.json();
    if (!Array.isArray(data) || data.length === 0) return mockProducts;

    return data.map(mapWpProduct);
  } catch {
    return mockProducts;
  }
}
