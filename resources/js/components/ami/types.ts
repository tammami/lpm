import type { Option } from '@/types';

export interface AuditSummary {
    id: number;
    code: string;
    audit_program_id: number;
    auditee_type: string;
    auditee_type_label: string;
    auditee_name: string;
    auditee_key: string;
    status: string;
    status_label: string;
    scheduled_on: string | null;
    desk_review_due: string | null;
    location: string | null;
    instrument: string | null;
    instrument_version_id: number | null;
    lead_auditor: string | null;
    lead_auditor_id: number | null;
    member_auditor_ids: string[];
    auditors: { id: number; name: string | null; role: string }[];
    auditee_pic: string | null;
    auditee_pic_user_id: number | null;
    findings_count: number;
    open_findings_count: number;
    completed_at: string | null;
}

export interface AuditFormOptions {
    auditees: (Option & { group?: string })[];
    auditorOptions: Option[];
    instrumentOptions: Option[];
    picOptions: Option[];
}

export interface FindingRow {
    id: number;
    code: string;
    title: string;
    auditee_name: string;
    audit_id: number | null;
    audit_code: string | null;
    severity: string;
    severity_code: string;
    severity_color: string;
    standard: string | null;
    status: string;
    status_label: string;
    pic: string | null;
    due_date: string | null;
    overdue: boolean;
    corrective_actions_count: number | null;
}
