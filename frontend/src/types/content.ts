import type { WebsiteContent } from './index';

export type WebsiteService = {
  slug: string;
  title: string;
  summary: string;
  content: string;
};

export type WebsiteContentWithServices = WebsiteContent & { services?: WebsiteService[] };
