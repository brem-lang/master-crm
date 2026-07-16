export type Permission = {
    id: number;
    name: string;
};

export type Role = {
    id: number;
    name: string;
    permissions?: Permission[];
};

export type Company = {
    id: number;
    name: string;
    slug?: string;
    website?: string | null;
    website_status?: 'online' | 'offline' | null;
    api_url?: string;
    api_key?: string;
    is_active?: boolean;
    users_count?: number;
};

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    roles?: Role[];
    company_id?: number | null;
    company?: Company | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Lead = {
    id: number;
    company_id: number;
    external_id: string;
    request_id: string | null;
    first_name: string | null;
    last_name: string | null;
    email: string | null;
    mobile: string | null;
    country_code: string | null;
    ip_address: string | null;
    status: string | null;
    affiliate_name: string | null;
    is_ftd: boolean;
    offer_name: string | null;
    meta: Record<string, unknown> | null;
    lead_created_at: string | null;
    company?: Pick<Company, 'id' | 'name'> | null;
};

export type Auth = {
    user: User;
    permissions?: string[];
    impersonating?: boolean;
    company?: Company | null;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
