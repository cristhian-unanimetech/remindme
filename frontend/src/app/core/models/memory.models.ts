export interface MemorySummary {
  id: number;
  title: string;
  contentPreview: string;
  memoryDate: string;
  moodColor: string | null;
  tags: string[];
  primaryImageUrl: string | null;
  spotify: {
    spotifyUrl: string | null;
    songTitle: string | null;
    artistName: string | null;
    coverUrl: string | null;
  };
}

export interface MemoryImage {
  id: number;
  url: string;
  isPrimary: boolean;
}

export interface MemoryDetail {
  id: number;
  title: string;
  content: string;
  memoryDate: string;
  moodColor: string | null;
  tags: string[];
  images: MemoryImage[];
  spotify: {
    spotifyUrl: string | null;
    songTitle: string | null;
    artistName: string | null;
    albumName: string | null;
    coverUrl: string | null;
    embedUrl: string | null;
    embedHtml: string | null;
  };
  createdAt: string | null;
  updatedAt: string | null;
}
