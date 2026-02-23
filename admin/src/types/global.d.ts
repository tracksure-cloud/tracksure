/**
 * Global type declarations for TrackSure Admin
 */

interface TrackSureAdminData {
  restUrl: string;
  restNonce: string;
  pluginVersion: string;
  apiUrl?: string;
  nonce?: string;
  currency?: string;
  currencySymbol?: string;
  timezone?: string;
  [key: string]: any;
}

declare global {
  interface Window {
    trackSureAdmin?: TrackSureAdminData;
    wp?: {
      apiFetch?: (options: any) => Promise<any>;
      [key: string]: any;
    };
  }
}

export {};
