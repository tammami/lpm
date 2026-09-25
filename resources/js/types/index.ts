export interface AuthUser {
    id: number;
    name: string;
    email: string;
    username: string | null;
    initials: string;
    role: string | null;
    role_label: string | null;
    roles: string[];
    scope_label: string;
    permissions: string[];
    must_change_password: boolean;
    is_lecturer: boolean;
    is_student: boolean;
}

export interface SharedProps {
    app: {
        name: string;
        institution: string;
        institution_short: string;
        logo_url: string | null;
    };
    auth: { user: AuthUser | null };
    activePeriod: { id: number; name: string; code: string } | null;
    unreadNotifications: number;
    errors: Record<string, string>;
    [key: string]: unknown;
}

export interface Option<T = string | number> {
    value: T;
    label: string;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

export interface BreadcrumbItem {
    label: string;
    href?: string;
}
