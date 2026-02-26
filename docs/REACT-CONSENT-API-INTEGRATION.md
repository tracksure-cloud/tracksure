# 🎯 TrackSure Consent API - React Integration Guide

**Production-Ready REST API for Consent Management**

---

## 📡 **REST API Endpoints**

All endpoints are available at `/wp-json/ts/v1/consent/*`

### **1. GET /consent/status** - Get Consent Configuration & Status

**Purpose**: Get current consent configuration and tracking status

**Authentication**: Requires `read` capability (any logged-in user)

**Response**:

```json
{
  "consent_mode": "opt-in",
  "is_tracking_allowed": false,
  "has_consent_plugin": true,
  "consent_metadata": {
    "consent_granted": "no",
    "consent_mode": "opt-in",
    "consent_plugin": "detected"
  }
}
```

**React Example**:

```typescript
import { useQuery } from "@tanstack/react-query";

export function useConsentStatus() {
  return useQuery({
    queryKey: ["consent", "status"],
    queryFn: async () => {
      const res = await fetch("/wp-json/ts/v1/consent/status", {
        credentials: "include", // Include WordPress auth cookies
      });
      if (!res.ok) throw new Error("Failed to fetch consent status");
      return res.json();
    },
    refetchInterval: 30000, // Refresh every 30 seconds
  });
}

// Usage in component
function ConsentStatusBadge() {
  const { data, isLoading } = useConsentStatus();

  if (isLoading) return <Spinner />;

  return (
    <Badge variant={data.is_tracking_allowed ? "success" : "warning"}>
      {data.consent_mode.toUpperCase()}
    </Badge>
  );
}
```

---

### **2. GET /consent/warning** - Get Consent Warning for Admin

**Purpose**: Check if consent warning should be displayed in React admin

**Authentication**: Requires `manage_options` capability (admin only)

**Response (Warning Needed)**:

```json
{
  "show_warning": true,
  "consent_mode": "opt-in",
  "message": "No consent management plugin was detected on your site.",
  "recommended_plugins": [
    {
      "name": "Cookie Notice by dFactory",
      "installs": "5M+",
      "url": "https://wordpress.org/plugins/cookie-notice/"
    },
    {
      "name": "GDPR Cookie Consent by WebToffee",
      "installs": "800K+",
      "url": "https://wordpress.org/plugins/cookie-law-info/"
    },
    {
      "name": "Cookiebot",
      "installs": "Enterprise",
      "url": "https://www.cookiebot.com/"
    },
    {
      "name": "Complianz",
      "installs": "GDPR/CCPA",
      "url": "https://wordpress.org/plugins/complianz-gdpr/"
    }
  ],
  "alternatives": [
    "Change consent mode to \"Disabled\" in Privacy Settings if your country doesn't require consent",
    "Use a 3rd party consent service (Osano, OneTrust, Usercentrics, etc.)"
  ],
  "info_message": "Without a consent plugin, TrackSure will anonymize user data in opt-in mode to ensure GDPR compliance while maintaining 100% event tracking."
}
```

**Response (No Warning)**:

```json
{
  "show_warning": false
}
```

**React Example**:

```typescript
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { ExternalLink } from "lucide-react";

export function ConsentWarningBanner() {
  const [dismissed, setDismissed] = useState(false);

  const { data } = useQuery({
    queryKey: ["consent", "warning"],
    queryFn: async () => {
      const res = await fetch("/wp-json/ts/v1/consent/warning", {
        credentials: "include",
      });
      return res.json();
    },
  });

  const handleDismiss = async () => {
    await fetch("/wp-json/ts/v1/consent/warning/dismiss", {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
    });
    setDismissed(true);
  };

  if (!data?.show_warning || dismissed) return null;

  return (
    <Alert variant="warning" dismissible onDismiss={handleDismiss}>
      <AlertTitle>⚠️ TrackSure: Consent Plugin Required</AlertTitle>
      <AlertDescription>
        <p className="mb-2">
          Your consent mode is set to: <code>{data.consent_mode}</code>
        </p>
        <p className="mb-4">{data.message}</p>

        <div className="mb-4">
          <strong>Recommended Plugins:</strong>
          <ul className="list-disc pl-5 mt-2">
            {data.recommended_plugins.map((plugin) => (
              <li key={plugin.name}>
                <a
                  href={plugin.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex items-center gap-1"
                >
                  <strong>{plugin.name}</strong> ({plugin.installs})
                  <ExternalLink className="w-3 h-3" />
                </a>
              </li>
            ))}
          </ul>
        </div>

        <div className="mb-4">
          <strong>Alternative Options:</strong>
          <ul className="list-disc pl-5 mt-2">
            {data.alternatives.map((alt, idx) => (
              <li key={idx}>{alt}</li>
            ))}
          </ul>
        </div>

        <p className="text-sm italic text-muted-foreground">
          ℹ️ {data.info_message}
        </p>
      </AlertDescription>
    </Alert>
  );
}
```

---

### **3. POST /consent/warning/dismiss** - Dismiss Warning

**Purpose**: Dismiss the consent warning (stores in user meta)

**Authentication**: Requires `manage_options` capability (admin only)

**Request**: No body required

**Response**:

```json
{
  "success": true,
  "message": "Consent warning dismissed successfully."
}
```

**React Example**:

```typescript
import { useMutation, useQueryClient } from "@tanstack/react-query";

export function useDismissConsentWarning() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async () => {
      const res = await fetch("/wp-json/ts/v1/consent/warning/dismiss", {
        method: "POST",
        credentials: "include",
        headers: { "Content-Type": "application/json" },
      });
      if (!res.ok) throw new Error("Failed to dismiss warning");
      return res.json();
    },
    onSuccess: () => {
      // Invalidate warning query to refetch
      queryClient.invalidateQueries({ queryKey: ["consent", "warning"] });
    },
  });
}

// Usage
function DismissButton() {
  const dismiss = useDismissConsentWarning();

  return (
    <Button
      variant="ghost"
      onClick={() => dismiss.mutate()}
      disabled={dismiss.isPending}
    >
      {dismiss.isPending ? "Dismissing..." : "Dismiss"}
    </Button>
  );
}
```

---

### **4. GET /consent/metadata** - Get Consent Metadata

**Purpose**: Get consent metadata for display in events table/debugging

**Authentication**: Requires `read` capability (any logged-in user)

**Response**:

```json
{
  "consent_granted": "yes",
  "consent_mode": "opt-in",
  "consent_plugin": "detected"
}
```

**React Example**:

```typescript
function EventConsentBadge({ eventId }: { eventId: string }) {
  const { data } = useQuery({
    queryKey: ["consent", "metadata"],
    queryFn: async () => {
      const res = await fetch("/wp-json/ts/v1/consent/metadata", {
        credentials: "include",
      });
      return res.json();
    },
    staleTime: 60000, // Cache for 1 minute
  });

  return (
    <Tooltip content={`Consent Mode: ${data.consent_mode}`}>
      <Badge variant={data.consent_granted === "yes" ? "success" : "secondary"}>
        {data.consent_granted === "yes" ? "✓ Consented" : "⊘ Anonymized"}
      </Badge>
    </Tooltip>
  );
}
```

---

## 🛠️ **Complete React Admin Integration**

### **Dashboard Privacy Settings Component**

```typescript
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { ConsentWarningBanner } from "./ConsentWarningBanner";
import { useConsentStatus } from "@/hooks/useConsentStatus";

export function PrivacySettingsSection() {
  const { data: status, isLoading } = useConsentStatus();

  if (isLoading) return <div>Loading privacy settings...</div>;

  return (
    <div className="space-y-6">
      {/* Warning Banner (only shows if needed) */}
      <ConsentWarningBanner />

      {/* Consent Configuration Card */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center justify-between">
            Consent Management
            <Badge
              variant={status.has_consent_plugin ? "success" : "secondary"}
            >
              {status.has_consent_plugin ? "✓ Plugin Detected" : "No Plugin"}
            </Badge>
          </CardTitle>
          <CardDescription>
            Configure how TrackSure handles user consent for GDPR/CCPA
            compliance
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {/* Consent Mode Selector */}
          <div>
            <label className="text-sm font-medium mb-2 block">
              Consent Mode
            </label>
            <Select
              value={status.consent_mode}
              onValueChange={handleConsentModeChange}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="disabled">
                  Disabled (No consent required)
                </SelectItem>
                <SelectItem value="opt-in">
                  Opt-in (GDPR - Explicit consent)
                </SelectItem>
                <SelectItem value="opt-out">
                  Opt-out (CCPA - Allow with opt-out)
                </SelectItem>
                <SelectItem value="auto">Auto (Detect by country)</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {/* Tracking Status */}
          <div className="p-4 bg-muted rounded-lg">
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium">
                Current Tracking Status:
              </span>
              <Badge
                variant={status.is_tracking_allowed ? "success" : "warning"}
              >
                {status.is_tracking_allowed ? "✓ Allowed" : "⊘ Anonymized"}
              </Badge>
            </div>
            <p className="text-sm text-muted-foreground mt-2">
              {status.is_tracking_allowed
                ? "Full tracking enabled with user identifiers"
                : "Tracking anonymized - PII removed, 100% events still captured"}
            </p>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
```

---

## 🔐 **Security & Authentication**

### **WordPress REST API Authentication**

The REST API uses WordPress's built-in authentication:

1. **Cookie Authentication** (Recommended for React admin):

   - Include `credentials: 'include'` in fetch requests
   - WordPress automatically verifies the nonce

2. **WordPress Nonce** (Already handled by WordPress):

   ```typescript
   // WordPress provides nonce via wp_localize_script
   const nonce = window.wpApiSettings?.nonce;

   fetch("/wp-json/ts/v1/consent/status", {
     credentials: "include",
     headers: {
       "X-WP-Nonce": nonce, // Optional - WordPress handles this automatically
     },
   });
   ```

---

## 📦 **React Query Setup (Recommended)**

### **Install Dependencies**

```bash
npm install @tanstack/react-query
```

### **Query Client Setup**

```typescript
// src/lib/queryClient.ts
import { QueryClient } from "@tanstack/react-query";

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30000, // 30 seconds
      refetchOnWindowFocus: false,
      retry: 1,
    },
  },
});

// src/App.tsx
import { QueryClientProvider } from "@tanstack/react-query";
import { queryClient } from "./lib/queryClient";

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      {/* Your React admin components */}
    </QueryClientProvider>
  );
}
```

### **Custom Hooks**

```typescript
// src/hooks/useConsentAPI.ts
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";

const CONSENT_API_BASE = "/wp-json/ts/v1/consent";

export function useConsentStatus() {
  return useQuery({
    queryKey: ["consent", "status"],
    queryFn: async () => {
      const res = await fetch(`${CONSENT_API_BASE}/status`, {
        credentials: "include",
      });
      if (!res.ok) throw new Error("Failed to fetch consent status");
      return res.json();
    },
  });
}

export function useConsentWarning() {
  return useQuery({
    queryKey: ["consent", "warning"],
    queryFn: async () => {
      const res = await fetch(`${CONSENT_API_BASE}/warning`, {
        credentials: "include",
      });
      if (!res.ok) throw new Error("Failed to fetch consent warning");
      return res.json();
    },
  });
}

export function useDismissConsentWarning() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async () => {
      const res = await fetch(`${CONSENT_API_BASE}/warning/dismiss`, {
        method: "POST",
        credentials: "include",
      });
      if (!res.ok) throw new Error("Failed to dismiss warning");
      return res.json();
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["consent", "warning"] });
    },
  });
}

export function useConsentMetadata() {
  return useQuery({
    queryKey: ["consent", "metadata"],
    queryFn: async () => {
      const res = await fetch(`${CONSENT_API_BASE}/metadata`, {
        credentials: "include",
      });
      if (!res.ok) throw new Error("Failed to fetch consent metadata");
      return res.json();
    },
    staleTime: 60000, // Cache for 1 minute
  });
}
```

---

## 🧪 **Testing the API**

### **Test with cURL**

```bash
# Get consent status
curl -X GET \
  'https://yoursite.com/wp-json/ts/v1/consent/status' \
  --cookie 'wordpress_logged_in_cookie=...'

# Get consent warning
curl -X GET \
  'https://yoursite.com/wp-json/ts/v1/consent/warning' \
  --cookie 'wordpress_logged_in_cookie=...'

# Dismiss consent warning
curl -X POST \
  'https://yoursite.com/wp-json/ts/v1/consent/warning/dismiss' \
  --cookie 'wordpress_logged_in_cookie=...'

# Get consent metadata
curl -X GET \
  'https://yoursite.com/wp-json/ts/v1/consent/metadata' \
  --cookie 'wordpress_logged_in_cookie=...'
```

### **Test in Browser Console**

```javascript
// Get consent status
fetch("/wp-json/ts/v1/consent/status", { credentials: "include" })
  .then((res) => res.json())
  .then(console.log);

// Get consent warning
fetch("/wp-json/ts/v1/consent/warning", { credentials: "include" })
  .then((res) => res.json())
  .then(console.log);

// Dismiss warning
fetch("/wp-json/ts/v1/consent/warning/dismiss", {
  method: "POST",
  credentials: "include",
})
  .then((res) => res.json())
  .then(console.log);
```

---

## ✅ **Production Checklist**

- [x] **REST API endpoints registered** (`class-tracksure-rest-consent-controller.php`)
- [x] **Controller loaded in REST API** (`class-tracksure-rest-api.php`)
- [x] **Public functions available** (`tracksure-consent-api.php`)
- [x] **Proper permission callbacks** (`manage_options` for admin, `read` for users)
- [x] **Error handling** (WP_Error responses)
- [x] **Backward compatibility** (Old AJAX handler still works)
- [x] **Security** (WordPress nonce + capability checks)
- [x] **React-friendly JSON responses** (No HTML output)

---

## 🎯 **Summary**

Your React admin now has **4 production-ready REST endpoints** for consent management:

1. **GET /consent/status** - Overall consent configuration
2. **GET /consent/warning** - Admin warning data
3. **POST /consent/warning/dismiss** - Dismiss warning
4. **GET /consent/metadata** - Consent metadata for debugging

All endpoints are:

- ✅ Properly authenticated
- ✅ Permission-checked
- ✅ Error-handled
- ✅ React-ready (JSON responses)
- ✅ Backward compatible (old AJAX still works)

**Your consent system is now production-ready!** 🚀
