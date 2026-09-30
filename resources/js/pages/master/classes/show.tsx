import { router, useForm } from '@inertiajs/react';
import { BookOpen, CalendarRange, GraduationCap, Pencil, Plus, Trash2, UserMinus, UserPlus, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Combobox } from '@/components/combobox';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { SearchInput } from '@/components/search-input';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import type { Option } from '@/types';
import { initials } from '../lecturers';

interface Props {
    courseClass: {
        id: number;
        code: string;
        capacity: number | null;
        course: { id: number; code: string; name: string; credits: number; semester: number };
        study_program: string;
        period: { id: number; name: string };
        assignments: { id: number; role: string; lecturer_id: number; name: string; nidn: string | null; study_program: string | null }[];
        students: { id: number; nim: string; name: string; entry_year: number; study_program: string | null }[];
    };
    lecturers: Option[];
    entryYears: Option[];
    canManage: boolean;
}

const roleLabels: Record<string, string> = { koordinator: 'Koordinator', anggota: 'Anggota tim', pengampu: 'Pengampu' };

export default function ClassShow({ courseClass, lecturers, entryYears, canManage }: Props) {
    const [search, setSearch] = useState('');
    const [enrolling, setEnrolling] = useState(false);
    const [editing, setEditing] = useState(false);
    const lecturerForm = useForm({ lecturer_id: '', role: courseClass.assignments.length ? 'anggota' : 'koordinator' });

    const students = useMemo(() => {
        const term = search.toLowerCase();
        return courseClass.students.filter((s) => !term || s.name.toLowerCase().includes(term) || s.nim.includes(term));
    }, [search, courseClass.students]);

    const available = lecturers.filter((l) => !courseClass.assignments.some((a) => a.lecturer_id === Number(l.value)));

    return (
        <>
            <PageHeader
                title={`${courseClass.course.name} — Kelas ${courseClass.code}`}
                breadcrumbs={[{ label: 'Master Akademik' }, { label: 'Kelas & Pengampu', href: route('classes.index', { period_id: courseClass.period.id }) }, { label: `Kelas ${courseClass.code}` }]}
                meta={
                    <>
                        <StatusBadge tone="primary" dot={false}>
                            <BookOpen className="size-3" /> {courseClass.course.code} · {courseClass.course.credits} SKS
                        </StatusBadge>
                        <StatusBadge tone="gold" dot={false}>
                            <CalendarRange className="size-3" /> {courseClass.period.name}
                        </StatusBadge>
                        <StatusBadge tone="neutral" dot={false}>
                            <GraduationCap className="size-3" /> {courseClass.study_program}
                        </StatusBadge>
                    </>
                }
                actions={
                    canManage && (
                        <>
                            <Button variant="outline" onClick={() => setEditing(true)}>
                                <Pencil /> Ubah kelas
                            </Button>
                            <ConfirmDialog
                                trigger={
                                    <Button variant="outline" className="text-destructive">
                                        <Trash2 /> Hapus kelas
                                    </Button>
                                }
                                title="Hapus kelas ini?"
                                description="Kelas yang sudah dievaluasi pada Monev tidak dapat dihapus."
                                href={route('classes.destroy', courseClass.id)}
                                confirmLabel="Hapus"
                            />
                        </>
                    )
                }
            />
            {editing && <EditClassDialog courseClass={courseClass} onClose={() => setEditing(false)} />}

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[380px_1fr]">
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Dosen pengampu</CardTitle>
                        <CardDescription>Setiap dosen dievaluasi terpisah oleh peserta kelas.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {courseClass.assignments.length === 0 && <p className="text-sm text-muted-foreground">Belum ada dosen pengampu.</p>}
                        {courseClass.assignments.map((assignment) => (
                            <div key={assignment.id} className="flex items-center gap-3 rounded-xl border p-3">
                                <Avatar className="size-10">
                                    <AvatarFallback className="bg-primary text-xs font-bold text-primary-foreground">{initials(assignment.name)}</AvatarFallback>
                                </Avatar>
                                <div className="min-w-0 flex-1">
                                    <div className="truncate text-sm font-semibold">{assignment.name}</div>
                                    <div className="text-xs text-muted-foreground">
                                        {roleLabels[assignment.role] ?? assignment.role} · NIDN {assignment.nidn ?? '—'}
                                    </div>
                                </div>
                                {canManage && (
                                    <ConfirmDialog
                                        trigger={
                                            <Button variant="ghost" size="icon-sm" aria-label="Lepas dosen">
                                                <UserMinus className="text-destructive" />
                                            </Button>
                                        }
                                        title={`Lepas ${assignment.name} dari kelas?`}
                                        description="Penugasan yang sudah memiliki respons Monev tidak dapat dilepas."
                                        href={route('classes.lecturers.destroy', [courseClass.id, assignment.id])}
                                        confirmLabel="Lepas"
                                    />
                                )}
                            </div>
                        ))}
                        {canManage && (
                            <form
                                className="mt-2 flex flex-col gap-2 rounded-xl border border-dashed p-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    lecturerForm.post(route('classes.lecturers.store', courseClass.id), { preserveScroll: true, onSuccess: () => lecturerForm.reset('lecturer_id') });
                                }}
                            >
                                <Combobox value={lecturerForm.data.lecturer_id} onChange={(v) => lecturerForm.setData('lecturer_id', v)} options={available} placeholder="Tambah dosen…" />
                                <div className="flex gap-2">
                                    <SelectField
                                        value={lecturerForm.data.role}
                                        onChange={(v) => lecturerForm.setData('role', v)}
                                        options={Object.entries(roleLabels).map(([value, label]) => ({ value, label }))}
                                    />
                                    <Button type="submit" disabled={!lecturerForm.data.lecturer_id || lecturerForm.processing}>
                                        <Plus /> Tambah
                                    </Button>
                                </div>
                                {lecturerForm.errors.lecturer_id && <p className="text-xs text-destructive">{lecturerForm.errors.lecturer_id}</p>}
                            </form>
                        )}
                    </CardContent>
                </Card>

                <Card className="gap-0 overflow-hidden py-0">
                    <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 className="flex items-center gap-2 font-bold">
                                <Users className="size-4 text-primary" /> Peserta kelas
                                <span className="rounded-full bg-secondary px-2 text-xs text-secondary-foreground tabular">
                                    {courseClass.students.length}
                                    {courseClass.capacity ? ` / ${courseClass.capacity}` : ''}
                                </span>
                            </h3>
                            <p className="text-xs text-muted-foreground">Hanya peserta kelas yang dapat mengevaluasi dosen pengampu.</p>
                        </div>
                        <div className="flex gap-2">
                            <SearchInput value={search} onChange={setSearch} placeholder="Cari NIM/nama…" />
                            {canManage && (
                                <Button onClick={() => setEnrolling(true)}>
                                    <UserPlus /> Tambah
                                </Button>
                            )}
                        </div>
                    </div>
                    {students.length === 0 ? (
                        <EmptyState icon={Users} title="Belum ada peserta" description="Tambahkan mahasiswa per angkatan atau tempel daftar NIM." />
                    ) : (
                        <div className="max-h-[560px] overflow-y-auto">
                            <Table>
                                <TableHeader className="sticky top-0 bg-muted/80 backdrop-blur">
                                    <TableRow>
                                        <TableHead className="px-4">NIM</TableHead>
                                        <TableHead>Nama</TableHead>
                                        <TableHead>Angkatan</TableHead>
                                        <TableHead className="w-12" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {students.map((student) => (
                                        <TableRow key={student.id}>
                                            <TableCell className="px-4 font-mono text-xs">{student.nim}</TableCell>
                                            <TableCell className="font-medium">{student.name}</TableCell>
                                            <TableCell className="tabular">{student.entry_year}</TableCell>
                                            <TableCell>
                                                {canManage && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        aria-label="Keluarkan"
                                                        onClick={() => router.delete(route('classes.students.destroy', [courseClass.id, student.id]), { preserveScroll: true })}
                                                    >
                                                        <UserMinus className="text-muted-foreground" />
                                                    </Button>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </Card>
            </div>

            {enrolling && <EnrollDialog classId={courseClass.id} entryYears={entryYears} onClose={() => setEnrolling(false)} />}
        </>
    );
}

function EnrollDialog({ classId, entryYears, onClose }: { classId: number; entryYears: Option[]; onClose: () => void }) {
    const form = useForm({ mode: 'cohort', entry_year: entryYears[0] ? String(entryYears[0].value) : '', nims: '' });

    return (
        <FormDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title="Tambah peserta kelas"
            onSubmit={() => form.post(route('classes.students.store', classId), { preserveScroll: true, onSuccess: onClose })}
            processing={form.processing}
            submitLabel="Tambahkan"
        >
            <Tabs value={form.data.mode} onValueChange={(v) => form.setData('mode', v)}>
                <TabsList className="mb-4 grid w-full grid-cols-2">
                    <TabsTrigger value="cohort">Satu angkatan</TabsTrigger>
                    <TabsTrigger value="nim">Daftar NIM</TabsTrigger>
                </TabsList>
                <TabsContent value="cohort">
                    <FormField label="Angkatan" error={form.errors.entry_year} hint="Semua mahasiswa aktif angkatan ini pada prodi terkait akan ditambahkan.">
                        <SelectField value={form.data.entry_year} onChange={(v) => form.setData('entry_year', v)} options={entryYears} />
                    </FormField>
                </TabsContent>
                <TabsContent value="nim">
                    <FormField label="NIM" error={form.errors.nims} hint="Pisahkan dengan baris baru, koma, atau spasi. Bisa ditempel langsung dari Excel.">
                        <Textarea rows={7} value={form.data.nims} onChange={(e) => form.setData('nims', e.target.value)} placeholder={'2511001\n2511002\n2511003'} className="font-mono" />
                    </FormField>
                </TabsContent>
            </Tabs>
        </FormDialog>
    );
}

function EditClassDialog({ courseClass, onClose }: { courseClass: Props['courseClass']; onClose: () => void }) {
    const form = useForm({ code: courseClass.code, capacity: courseClass.capacity === null ? '' : String(courseClass.capacity) });

    return (
        <FormDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title="Ubah kelas"
            description={`${courseClass.course.name} · ${courseClass.period.name}`}
            onSubmit={() => form.put(route('classes.update', courseClass.id), { preserveScroll: true, onSuccess: onClose })}
            processing={form.processing}
        >
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField label="Kode kelas" error={form.errors.code} required hint="Mis. A, B, atau PAI-1.">
                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} />
                </FormField>
                <FormField label="Kapasitas" error={form.errors.capacity} hint="Kosongkan bila tidak dibatasi.">
                    <Input type="number" min={1} max={500} value={form.data.capacity} onChange={(e) => form.setData('capacity', e.target.value)} />
                </FormField>
            </div>
        </FormDialog>
    );
}
