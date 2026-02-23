/**
 * Channel Classification Helpers for TrackSure
 * 
 * Provides channel classification layer on top of source/medium
 * to give users intuitive groupings without relying solely on UTMs.
 */

import type { IconName } from '../config/iconRegistry';

/**
 * Classify channel from source and medium
 * 
 * Primary layer for acquisition analysis - groups traffic into
 * meaningful categories that users can understand at a glance.
 */
export const classifyChannel = (source: string | null | undefined, medium: string | null | undefined): string => {
  // Normalize inputs
  const src = (source || '').toLowerCase().trim();
  const med = (medium || '').toLowerCase().trim();
  
  // Direct traffic
  if (!src || src === 'direct' || src === '(direct)' || med === '(none)' || med === 'none' || (!src && !med)) {
    return 'Direct';
  }
  
  // Organic Search
  const searchEngines = ['google', 'bing', 'yahoo', 'duckduckgo', 'baidu', 'yandex', 'ecosia', 'ask'];
  if (med === 'organic') {
    if (searchEngines.some(engine => src.includes(engine))) {
      return 'Organic Search';
    }
  }
  
  // Paid Search
  if (['cpc', 'ppc', 'paidsearch', 'paid-search'].includes(med)) {
    if (searchEngines.some(engine => src.includes(engine))) {
      return 'Paid Search';
    }
  }
  
  // Paid Social
  const socialPlatforms = ['facebook', 'instagram', 'linkedin', 'twitter', 'tiktok', 'pinterest', 'snapchat', 'reddit'];
  if (med === 'paid_social' || med === 'paid-social' || med === 'paidsocial') {
    return 'Paid Social';
  }
  
  // CPC could be paid social or paid search depending on source
  if (med === 'cpc') {
    if (socialPlatforms.some(platform => src.includes(platform))) {
      return 'Paid Social';
    }
    if (searchEngines.some(engine => src.includes(engine))) {
      return 'Paid Search';
    }
  }
  
  // Social (Organic)
  if (med === 'social' || socialPlatforms.some(platform => src.includes(platform))) {
    return 'Social';
  }
  
  // Email
  if (med === 'email' || src.includes('mail') || src.includes('newsletter') || src.includes('campaign')) {
    return 'Email';
  }
  
  // Referral
  if (med === 'referral' || med === 'refer') {
    return 'Referral';
  }
  
  // Display
  if (med === 'display' || med === 'banner' || med === 'cpm') {
    return 'Display';
  }
  
  // Affiliates
  if (med === 'affiliate' || src.includes('affiliate') || src.includes('aff')) {
    return 'Affiliates';
  }
  
  // Messaging apps
  const messagingApps = ['whatsapp', 'telegram', 'messenger', 'wechat', 'line'];
  if (messagingApps.some(app => src.includes(app)) || med === 'messaging') {
    return 'Messaging';
  }
  
  // AI Chatbots / Assistants
  const aiSources = ['chatgpt', 'claude', 'perplexity', 'gemini', 'bard', 'copilot', 'bing-ai'];
  if (aiSources.some(ai => src.includes(ai)) || med === 'ai' || med === 'chatbot') {
    return 'AI Assistant';
  }
  
  // Video platforms
  const videoPlatforms = ['youtube', 'vimeo', 'dailymotion'];
  if (videoPlatforms.some(platform => src.includes(platform)) && med !== 'social') {
    return 'Video';
  }
  
  // If we couldn't classify, return Other
  return 'Other';
};

/**
 * Get color for channel (for charts and badges)
 */
export const getChannelColor = (channel: string): string => {
  const colors: Record<string, string> = {
    'Direct': '#6B7280',
    'Organic Search': '#10B981',
    'Paid Search': '#F59E0B',
    'Paid Social': '#8B5CF6',
    'Social': '#3B82F6',
    'Email': '#EF4444',
    'Referral': '#EC4899',
    'Display': '#14B8A6',
    'Affiliates': '#F97316',
    'Messaging': '#22C55E',
    'AI Assistant': '#A855F7',
    'Video': '#EF4444',
    'Other': '#9CA3AF',
  };
  return colors[channel] || colors['Other'];
};

/**
 * Get icon for channel
 * Returns Lucide icon name instead of emoji
 */
export const getChannelIcon = (channel: string): IconName => {
  const icons: Record<string, IconName> = {
    'Direct': 'Link',
    'Organic Search': 'Search',
    'Paid Search': 'DollarSign',
    'Paid Social': 'Smartphone',
    'Social': 'Users',
    'Email': 'Mail',
    'Referral': 'Link',
    'Display': 'Monitor',
    'Affiliates': 'Handshake',
    'Messaging': 'MessageSquare',
    'AI Assistant': 'Bot',
    'Video': 'Video',
    'Other': 'Globe',
  };
  return icons[channel] || icons['Other'];
};

/**
 * Get badge class for channel (for CSS styling)
 */
export const getChannelBadgeClass = (channel: string): string => {
  const classes: Record<string, string> = {
    'Direct': 'ts-badge-neutral',
    'Organic Search': 'ts-badge-success',
    'Paid Search': 'ts-badge-warning',
    'Paid Social': 'ts-badge-purple',
    'Social': 'ts-badge-info',
    'Email': 'ts-badge-danger',
    'Referral': 'ts-badge-pink',
    'Display': 'ts-badge-teal',
    'Affiliates': 'ts-badge-orange',
    'Messaging': 'ts-badge-green',
    'AI Assistant': 'ts-badge-violet',
    'Video': 'ts-badge-red',
    'Other': 'ts-badge-gray',
  };
  return classes[channel] || classes['Other'];
};

/**
 * Group sources by channel
 */
export interface SourceData {
  source: string;
  medium: string;
  sessions: number;
  conversions: number;
  revenue?: number;
  conversion_rate?: number;
  [key: string]: string | number | boolean | undefined | null;
}

export interface ChannelGroup {
  channel: string;
  sources: SourceData[];
  totalSessions: number;
  totalConversions: number;
  totalRevenue: number;
  conversionRate: number;
}

export const groupSourcesByChannel = (sources: SourceData[]): Record<string, ChannelGroup> => {
  const groups: Record<string, ChannelGroup> = {};
  
  sources.forEach((source) => {
    const channel = classifyChannel(source.source, source.medium);
    
    if (!groups[channel]) {
      groups[channel] = {
        channel,
        sources: [],
        totalSessions: 0,
        totalConversions: 0,
        totalRevenue: 0,
        conversionRate: 0,
      };
    }
    
    groups[channel].sources.push(source);
    groups[channel].totalSessions += source.sessions || 0;
    groups[channel].totalConversions += source.conversions || 0;
    groups[channel].totalRevenue += source.revenue || 0;
  });
  
  // Calculate conversion rates
  Object.values(groups).forEach(group => {
    if (group.totalSessions > 0) {
      group.conversionRate = (group.totalConversions / group.totalSessions) * 100;
    }
  });
  
  return groups;
};

/**
 * Get channel summary statistics
 */
export const getChannelSummary = (sources: SourceData[]): {
  totalChannels: number;
  topChannel: string;
  channelMix: Array<{ channel: string; percentage: number }>;
} => {
  const groups = groupSourcesByChannel(sources);
  const totalSessions = Object.values(groups).reduce((sum, g) => sum + g.totalSessions, 0);
  
  // Sort by sessions
  const sortedChannels = Object.entries(groups)
    .sort(([, a], [, b]) => b.totalSessions - a.totalSessions);
  
  const topChannel = sortedChannels.length > 0 ? sortedChannels[0][0] : 'Unknown';
  
  const channelMix = sortedChannels.map(([channel, group]) => ({
    channel,
    percentage: totalSessions > 0 ? (group.totalSessions / totalSessions) * 100 : 0,
  }));
  
  return {
    totalChannels: Object.keys(groups).length,
    topChannel,
    channelMix,
  };
};
