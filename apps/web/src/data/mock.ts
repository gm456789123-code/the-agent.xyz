export interface ProductDeal {
  id: string;
  title: string;
  category: "streaming" | "gaming" | "software" | "services" | "vouchers";
  categoryLabel: string;
  tagIds?: number[];
  storeName: string;
  storeRating: number;
  badge?: string;
  badgeType?: "hot" | "sale" | "verified";
  originalPrice?: string;
  price: string;
  unit: string;
  deliverySpeed: string;
  image: string;
  color: string;
}

export const mockCategories = [
  { id: "all", label: "ดีลทั้งหมด" },
  { id: "streaming", label: "แอป & สตรีมมิ่ง" },
  { id: "gaming", label: "เติมเกม & บัตรเติมเงิน" },
  { id: "software", label: "ซอฟต์แวร์ & คีย์ดิจิทัล" },
  { id: "services", label: "บริการออนไลน์ & บูสต์แรงค์" },
];

export const mockProducts: ProductDeal[] = [
  {
    id: "youtube-premium-family",
    title: "YouTube Premium 4K (หารครอบครัว 1 เดือน)",
    category: "streaming",
    categoryLabel: "แอปสตรีมมิ่ง",
    storeName: "StreamVip Official Store",
    storeRating: 4.9,
    badge: "ขายดีอันดับ 1",
    badgeType: "hot",
    originalPrice: "฿159",
    price: "฿39",
    unit: "ใช้งาน 30 วัน",
    deliverySpeed: "ดึงเข้ากลุ่มออโต้ 1 นาที",
    image: "https://images.unsplash.com/photo-1611162617213-7d7a39e9b1d7?auto=format&fit=crop&w=600&q=80",
    color: "from-rose-500/20 to-red-600/10",
  },
  {
    id: "valorant-vp",
    title: "Valorant Points (VP) — เรทพิเศษ",
    category: "gaming",
    categoryLabel: "เกม PC",
    storeName: "GameShopTH (Verified)",
    storeRating: 5.0,
    badge: "การันตีถูกสุด",
    badgeType: "sale",
    originalPrice: "฿350",
    price: "฿289",
    unit: "1,000 VP",
    deliverySpeed: "เติมตรง UID 15 วิ",
    image: "https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=600&q=80",
    color: "from-cyan-500/20 to-sky-600/10",
  },
  {
    id: "canva-pro-lifetime",
    title: "Canva Pro Educational Pass",
    category: "software",
    categoryLabel: "ดีไซน์ & ซอฟต์แวร์",
    storeName: "SoftKey Express",
    storeRating: 4.8,
    badge: "ร้านค้ายืนยันแล้ว",
    badgeType: "verified",
    originalPrice: "฿290",
    price: "฿89",
    unit: "เปิดใช้ฟีเจอร์ Pro ครบ",
    deliverySpeed: "ส่งลิงก์เชิญอัตโนมัติ",
    image: "https://images.unsplash.com/photo-1626785774573-4b799315345d?auto=format&fit=crop&w=600&q=80",
    color: "from-purple-500/20 to-violet-600/10",
  },
  {
    id: "steam-wallet-card",
    title: "Steam Wallet Card (THB โค้ดแท้)",
    category: "gaming",
    categoryLabel: "บัตรดิจิทัล",
    storeName: "KeyMaster Thailand",
    storeRating: 4.9,
    badge: "ส่งโค้ดทันที",
    badgeType: "hot",
    originalPrice: "฿1,000",
    price: "฿950",
    unit: "1,000 THB Code",
    deliverySpeed: "รับรหัสในหน้าเว็บทันที",
    image: "https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=600&q=80",
    color: "from-blue-600/20 to-indigo-700/10",
  },
  {
    id: "netflix-ultra-hd",
    title: "Netflix Ultra HD 4K Profile (1 จอ)",
    category: "streaming",
    categoryLabel: "ความบันเทิง",
    storeName: "MovieLover Hub",
    storeRating: 4.7,
    badge: "พินส่วนตัว 100%",
    badgeType: "verified",
    originalPrice: "฿169",
    price: "฿99",
    unit: "30 วัน (ล็อกรหัส PIN)",
    deliverySpeed: "จัดส่งรหัสผ่าน SMS/Web",
    image: "https://images.unsplash.com/photo-1574375927938-d5a98e8ffe85?auto=format&fit=crop&w=600&q=80",
    color: "from-pink-500/20 to-rose-600/10",
  },
  {
    id: "rank-boost-valorant",
    title: "Valorant Rank Boost (Ascendant - Radiant)",
    category: "services",
    categoryLabel: "บริการออนไลน์",
    storeName: "ProPlayer Guild TH",
    storeRating: 5.0,
    badge: "ช่างมืออาชีพ",
    badgeType: "hot",
    price: "฿450",
    unit: "เริ่มต้น 1 Tier (สตรีมสด)",
    deliverySpeed: "เริ่มรับงานใน 30 นาที",
    image: "https://images.unsplash.com/photo-1560253023-3ec5d502959f?auto=format&fit=crop&w=600&q=80",
    color: "from-emerald-500/20 to-teal-700/10",
  },
];

export interface SystemStatus {
  service: string;
  status: "ONLINE" | "BUSY" | "MAINTENANCE";
  speed: string;
}

export const mockSystemStatus: SystemStatus[] = [
  { service: "ระบบนายหน้าตรวจสลิป (PromptPay/TrueMoney)", status: "ONLINE", speed: "5-10 วินาที" },
  { service: "API จัดส่งสตรีมมิ่ง (StreamVip Gateway)", status: "ONLINE", speed: "ทันที" },
  { service: "ระบบเติมตรง UID (GameShop API)", status: "ONLINE", speed: "ปกติ" },
  { service: "คลังซอฟต์แวร์ & โค้ดคีย์ออโต้", status: "ONLINE", speed: "พร้อมส่ง 24/7" },
];

export const liveTransactions = [
  { user: "089-xxx-4122", item: "YouTube Premium 30 วัน", time: "เมื่อสักครู่", amount: "฿39", store: "StreamVip" },
  { user: "062-xxx-8901", item: "Valorant 1,000 VP (UID)", time: "1 นาทีที่แล้ว", amount: "฿289", store: "GameShopTH" },
  { user: "091-xxx-1145", item: "Canva Pro Educational", time: "2 นาทีที่แล้ว", amount: "฿89", store: "SoftKey" },
  { user: "084-xxx-6539", item: "Steam Wallet 1,000 ฿", time: "3 นาทีที่แล้ว", amount: "฿950", store: "KeyMaster" },
];

export const flashDealEndsAt = new Date(Date.now() + 4 * 60 * 60 * 1000 + 35 * 60 * 1000).toISOString();
export const flashDealPercentOff = 35;
