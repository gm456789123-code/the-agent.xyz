import { mockProducts, type ProductDeal } from "../data/mock";

const WP_API_URL = import.meta.env.PUBLIC_WP_API_URL || "https://azure-bison-824750.hostingersite.com/wp-json";

interface WpProduct {
  id: number;
  title: { rendered: string };
  excerpt: { rendered: string };
  meta?: { price?: number; category?: string; store_name?: string; unit?: string };
  featured_image_url?: string | null;
  "product-tags"?: number[];
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
    tagIds: product["product-tags"] || [],
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
      `${WP_API_URL}/wp/v2/product?_fields=id,title,excerpt,meta,featured_image_url,product-tags&orderby=id&order=desc&per_page=50`,
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

async function getHomeProducts(section: "featured" | "latest", limit: number) {
  if (!WP_API_URL) return { products: [] as ProductDeal[], unavailable: true };
  try {
    const response = await fetch(`${WP_API_URL}/nexus/v1/products/${section}`, {
      signal: AbortSignal.timeout(5000),
    });
    if (!response.ok) throw new Error("Products unavailable");
    const data: WpProduct[] = await response.json();
    if (!Array.isArray(data)) throw new Error("Invalid product response");
    return {
      products: data.filter((product) => product.meta?.category !== "services").slice(0, limit).map(mapWpProduct),
      unavailable: false,
    };
  } catch {
    return { products: [] as ProductDeal[], unavailable: true };
  }
}

export async function getHomepageProducts() {
  const [featured, latest] = await Promise.all([
    getHomeProducts("featured", 9),
    getHomeProducts("latest", 6),
  ]);
  return { featured, latest };
}

export interface ProductTag { id: number; name: string; slug: string }

export async function getProductTags(): Promise<ProductTag[]> {
  if (!WP_API_URL) return [];
  try {
    const tags: ProductTag[] = [];
    let totalPages = 1;
    for (let page = 1; page <= totalPages; page++) {
      const response = await fetch(`${WP_API_URL}/wp/v2/product-tags?hide_empty=false&per_page=100&page=${page}&orderby=name&order=asc&_fields=id,name,slug`, {
        signal: AbortSignal.timeout(5000),
      });
      if (!response.ok) throw new Error("Product tags unavailable");
      const data = await response.json();
      if (!Array.isArray(data)) throw new Error("Invalid product tags");
      tags.push(...data);
      totalPages = Number(response.headers.get("X-WP-TotalPages")) || 1;
    }
    return tags;
  } catch {
    return [];
  }
}
