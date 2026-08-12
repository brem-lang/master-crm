export type Permission = {
    id: number;
    name: string;
};

export type PermissionOption = {
    name: string;
    description: string | null;
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
    leads_count_url?: string | null;
    affiliates_url?: string | null;
    advertisers_url?: string | null;
    affiliate_count_api_url?: string | null;
    advertiser_count_api_url?: string | null;
    send_test_lead_url?: string | null;
    release_ftd_url?: string | null;
    send_lead_url?: string | null;
    update_affiliate_status_url?: string | null;
    is_active?: boolean;
    users_count?: number;
    last_synced_at?: string | null;
    failure_streak?: number;
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
    is_active?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Lead = {
    id: number;
    company_id: number;
    assigned_to: number | null;
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
    ftd_released: boolean;
    offer_name: string | null;
    advertiser_name: string | null;
    sale_status: string | null;
    live_lead_status: string | null;
    meta: Record<string, unknown> | null;
    lead_created_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    company?: Pick<Company, 'id' | 'name'> | null;
    assignee?: Pick<User, 'id' | 'name'> | null;
};

export type Affiliate = {
    id: number;
    company_id: number;
    external_id: string;
    name: string;
    api_key: string | null;
    is_active: boolean;
    meta: Record<string, unknown> | null;
    synced_at: string | null;
    company?: Pick<Company, 'id' | 'name'> | null;
};

export type Advertiser = {
    id: number;
    company_id: number;
    external_id: string;
    name: string;
    advertiser_type: string | null;
    url: string | null;
    is_active: boolean;
    daily_cap: number | null;
    hourly_cap: number | null;
    default_deal_type: string | null;
    meta: Record<string, unknown> | null;
    synced_at: string | null;
    company?: Pick<Company, 'id' | 'name'> | null;
};

export type JobRun = {
    id: number;
    company_id: number;
    triggered_by: 'manual' | 'scheduled';
    success: boolean;
    pulled: number;
    deleted: number;
    message: string;
    attempt: number | null;
    created_at: string;
    company?: Pick<Company, 'id' | 'name'> | null;
};

export type AuditLog = {
    id: number;
    actor: Pick<User, 'id' | 'name' | 'email'> | null;
    ip_address: string | null;
    action: string;
    subject_type: string | null;
    subject_id: number | null;
    changes: Record<string, unknown> | null;
    created_at: string;
};

export type Auth = {
    user: User;
    permissions?: string[];
    impersonating?: boolean;
    company?: Company | null;
};

export type GlobalSearchResults = {
    companies: Pick<Company, 'id' | 'name' | 'slug'>[];
    users: (Pick<User, 'id' | 'name' | 'email'> & {
        company: Pick<Company, 'id' | 'name'> | null;
    })[];
    leads: {
        id: number;
        first_name: string | null;
        last_name: string | null;
        email: string | null;
        company_id: number;
        company: Pick<Company, 'id' | 'name'> | null;
    }[];
};

export type JobRunNotification = {
    id: string;
    read_at: string | null;
    created_at: string;
    data: {
        job_run_id: number;
        company_id: number;
        company_name: string | null;
        success: boolean;
        pulled: number;
        message: string;
        triggered_by: 'manual' | 'scheduled';
    };
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
