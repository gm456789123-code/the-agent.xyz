export interface ProductTile {
  id: string;
  title: string;
  subtitle: string;
  price: string;
  accent: "green" | "purple";
  size: "large" | "medium" | "small";
}

export const mockProducts: ProductTile[] = [
  {
    id: "limited-key",
    title: "LIMITED EDITION GAME KEY",
    subtitle: "NEXUS ARCADE",
    price: "$46.05",
    accent: "green",
    size: "large",
  },
  {
    id: "skin-pack",
    title: "3D SKIN PACK",
    subtitle: "Limited Pack",
    price: "$32.20",
    accent: "purple",
    size: "medium",
  },
  {
    id: "rank-boost-1",
    title: "RANK BOOST",
    subtitle: "Solo Queue",
    price: "$21.90",
    accent: "green",
    size: "small",
  },
  {
    id: "rank-boost-2",
    title: "RANK BOOST",
    subtitle: "Duo Queue",
    price: "$46.59",
    accent: "green",
    size: "small",
  },
  {
    id: "coaching-1",
    title: "COACHING",
    subtitle: "1-on-1 Session",
    price: "$21.58",
    accent: "purple",
    size: "small",
  },
  {
    id: "coaching-2",
    title: "COACHING",
    subtitle: "Team Review",
    price: "$38.20",
    accent: "purple",
    size: "small",
  },
  {
    id: "service",
    title: "SERVICE",
    subtitle: "Account Setup",
    price: "$32.60",
    accent: "green",
    size: "small",
  },
];

export interface ServerStatus {
  region: string;
  status: "ONLINE" | "OFFLINE" | "DEGRADED";
}

export const mockServerStatus: ServerStatus[] = [
  { region: "NA-EAST", status: "ONLINE" },
  { region: "NA-WEST", status: "ONLINE" },
  { region: "EU-WEST", status: "ONLINE" },
  { region: "AP-SOUTH", status: "ONLINE" },
];

// ISO timestamp the flash deal countdown ticks down to.
export const flashDealEndsAt = new Date(Date.now() + 2 * 60 * 60 * 1000 + 15 * 60 * 1000).toISOString();
export const flashDealPercentOff = 40;
