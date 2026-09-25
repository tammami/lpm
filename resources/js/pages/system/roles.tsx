import { router } from '@inertiajs/react';
import { Check, Lock, RotateCcw, Save } from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

interface Role {
    name: string;
    label: string;
    users_count: number;
    permissions: string[];
    locked: boolean;
    scope: string;
}

interface Props {
    roles: Role[];
    groups: { group: string; permissions: { name: string; label: string }[] }[];
}

export default function Roles({ roles, groups }: Props) {
    const initial = useMemo(() => Object.fromEntries(roles.map((r) => [r.name, new Set(r.permissions)])), [roles]);
    const [matrix, setMatrix] = useState<Record<string, Set<string>>>(initial);
    const [saving, setSaving] = useState<string | null>(null);

    const dirty = (role: string) => {
        const a = matrix[role];
        const b = initial[role];
        return a.size !== b.size || [...a].some((p) => !b.has(p));
    };

    const toggle = (role: Role, permission: string) => {
        if (role.locked) return;
        setMatrix((m) => {
            const next = new Set(m[role.name]);
            if (next.has(permission)) next.delete(permission);
            else next.add(permission);
            return { ...m, [role.name]: next };
        });
    };

    const save = (role: string) => {
        setSaving(role);
        router.put(route('roles.update', role), { permissions: [...matrix[role]] }, { preserveScroll: true, onFinish: () => setSaving(null) });
    };

    return (
        <>
            <PageHeader
                title="Peran & Hak Akses"
                description="Matriks RBAC. Cakupan data (institusi → fakultas → prodi) diterapkan otomatis sesuai peran; hak akses di bawah menentukan fitur yang boleh dipakai."
                breadcrumbs={[{ label: 'Sistem' }, { label: 'Peran & Hak Akses' }]}
            />
            <Card className="gap-0 overflow-hidden p-0">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[980px] text-sm">
                        <thead className="sticky top-0 z-10 bg-card">
                            <tr className="border-b">
                                <th className="w-72 px-5 py-4 text-left text-xs font-bold tracking-wide text-muted-foreground uppercase">Hak akses</th>
                                {roles.map((role) => (
                                    <th key={role.name} className="px-2 py-4 text-center align-bottom">
                                        <div className="flex flex-col items-center gap-1">
                                            {role.locked && <Lock className="size-3 text-gold-foreground" />}
                                            <span className="text-[12px] font-bold">{role.label}</span>
                                            <span className="text-[11px] font-normal text-muted-foreground">{role.users_count} akun</span>
                                        </div>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            <tr className="border-b bg-muted/40">
                                <td className="px-5 py-2 text-xs font-semibold text-muted-foreground">Cakupan data</td>
                                {roles.map((role) => (
                                    <td key={role.name} className="px-2 py-2 text-center text-[11px] leading-tight text-muted-foreground">
                                        {role.scope}
                                    </td>
                                ))}
                            </tr>
                            {groups.map((group) => (
                                <Fragment key={group.group}>
                                    <tr className="bg-secondary/40">
                                        <td colSpan={roles.length + 1} className="px-5 py-2 text-[11px] font-bold tracking-wider text-secondary-foreground uppercase">
                                            {group.group}
                                        </td>
                                    </tr>
                                    {group.permissions.map((permission) => (
                                        <tr key={permission.name} className="border-b border-border/60 hover:bg-muted/30">
                                            <td className="px-5 py-2.5">
                                                <div className="text-[13px] font-medium">{permission.label}</div>
                                                <div className="font-mono text-[11px] text-muted-foreground">{permission.name}</div>
                                            </td>
                                            {roles.map((role) => {
                                                const checked = matrix[role.name].has(permission.name);
                                                return (
                                                    <td key={role.name} className="px-2 py-2 text-center">
                                                        <button
                                                            type="button"
                                                            role="checkbox"
                                                            aria-checked={checked}
                                                            aria-label={`${role.label}: ${permission.label}`}
                                                            disabled={role.locked}
                                                            onClick={() => toggle(role, permission.name)}
                                                            className={cn(
                                                                'mx-auto flex size-6 items-center justify-center rounded-md border-2 transition',
                                                                checked ? 'border-primary bg-primary text-primary-foreground' : 'border-input bg-card hover:border-primary/50',
                                                                role.locked && 'cursor-not-allowed opacity-60',
                                                            )}
                                                        >
                                                            {checked && <Check className="size-3.5" strokeWidth={3} />}
                                                        </button>
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </Fragment>
                            ))}
                        </tbody>
                        <tfoot className="sticky bottom-0 bg-card">
                            <tr className="border-t">
                                <td className="px-5 py-3 text-xs text-muted-foreground">Simpan per peran</td>
                                {roles.map((role) => (
                                    <td key={role.name} className="px-2 py-3 text-center">
                                        {!role.locked && (
                                            <div className="flex flex-col items-center gap-1">
                                                <Button size="xs" disabled={!dirty(role.name) || saving === role.name} onClick={() => save(role.name)}>
                                                    <Save /> Simpan
                                                </Button>
                                                <ConfirmDialog
                                                    trigger={
                                                        <Button size="xs" variant="ghost" className="text-muted-foreground">
                                                            <RotateCcw /> Bawaan
                                                        </Button>
                                                    }
                                                    title={`Kembalikan hak akses ${role.label} ke bawaan?`}
                                                    href={route('roles.reset', role.name)}
                                                    method="post"
                                                    destructive={false}
                                                    confirmLabel="Kembalikan"
                                                />
                                            </div>
                                        )}
                                    </td>
                                ))}
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </Card>
        </>
    );
}
