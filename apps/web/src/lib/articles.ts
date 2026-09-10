const apiUrl = import.meta.env.PUBLIC_WP_API_URL || "https://azure-bison-824750.hostingersite.com/wp-json";

export interface Article {
  id: number;
  title: { rendered: string };
  excerpt: { rendered: string };
  content?: { rendered: string };
  date: string;
}

export async function getArticles(page = 1, perPage = 12) {
  if (!apiUrl) return { articles: [] as Article[], totalPages: 0, unavailable: true };
  try {
    const response = await fetch(`${apiUrl}/wp/v2/posts?status=publish&page=${page}&per_page=${perPage}&orderby=date&order=desc&_fields=id,title,excerpt,content,date`, {
      signal: AbortSignal.timeout(5000),
    });
    if (!response.ok) throw new Error("Articles unavailable");
    const articles: Article[] = await response.json();
    if (!Array.isArray(articles)) throw new Error("Invalid articles response");
    return { articles, totalPages: Number(response.headers.get("X-WP-TotalPages")) || 1, unavailable: false };
  } catch {
    return { articles: [] as Article[], totalPages: 0, unavailable: true };
  }
}

export async function getAllArticles() {
  const firstPage = await getArticles(1, 100);
  const articles = [...firstPage.articles];
  for (let page = 2; page <= firstPage.totalPages; page++) {
    const nextPage = await getArticles(page, 100);
    if (nextPage.unavailable) return { articles: [] as Article[], unavailable: true };
    articles.push(...nextPage.articles);
  }
  return { articles, unavailable: firstPage.unavailable };
}

export function articleTitle(article: Article) {
  return article.title.rendered.replace(/<[^>]*>/g, "");
}

export function articleDate(date: string) {
  return new Intl.DateTimeFormat("th-TH", { day: "numeric", month: "long", year: "numeric" }).format(new Date(date));
}
