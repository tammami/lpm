import { GraduationCap, LoaderCircle, Plus, UserPlus, UserRound, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import type { TaggedLecturer, TaggedStudent } from '@/lib/research';

interface Person {
    id: number;
    name: string;
    number: string | null;
    study_program: string | null;
}

/** Pencarian orang di server dengan jeda ketik, dibatalkan bila kata kunci berubah. */
function usePeopleSearch(kind: 'dosen' | 'mahasiswa', query: string, enabled: boolean) {
    const [items, setItems] = useState<Person[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!enabled) return;
        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true);
            fetch(route('research.people', { jenis: kind, q: query }), { headers: { Accept: 'application/json' }, signal: controller.signal })
                .then((response) => response.json())
                .then((data: Person[]) => setItems(data))
                .catch(() => undefined)
                .finally(() => setLoading(false));
        }, 250);
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [kind, query, enabled]);

    return { items, loading };
}

function Chip({ icon: Icon, label, sub, onRemove }: { icon: typeof UserRound; label: string; sub?: string | null; onRemove: () => void }) {
    return (
        <span className="inline-flex max-w-full items-center gap-1.5 rounded-lg border border-primary/15 bg-secondary py-1 pr-1 pl-2 text-xs text-secondary-foreground">
            <Icon className="size-3.5 shrink-0 opacity-70" />
            <span className="truncate font-semibold">{label}</span>
            {sub && <span className="shrink-0 text-muted-foreground tabular">{sub}</span>}
            <button type="button" onClick={onRemove} className="rounded p-0.5 opacity-60 hover:bg-white/60 hover:opacity-100" aria-label={`Hapus ${label}`}>
                <X className="size-3" />
            </button>
        </span>
    );
}

function PickerShell({
    label,
    open,
    onOpenChange,
    query,
    onQuery,
    placeholder,
    loading,
    invalid,
    children,
}: {
    label: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    query: string;
    onQuery: (query: string) => void;
    placeholder: string;
    loading: boolean;
    invalid?: boolean;
    children: React.ReactNode;
}) {
    return (
        <Popover open={open} onOpenChange={onOpenChange}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    aria-invalid={invalid}
                    className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-dashed border-primary/40 bg-card px-2.5 text-xs font-semibold text-primary transition hover:border-primary hover:bg-secondary aria-invalid:border-destructive"
                >
                    <Plus className="size-3.5" /> {label}
                </button>
            </PopoverTrigger>
            <PopoverContent className="w-80 p-0" align="start">
                <Command shouldFilter={false}>
                    <CommandInput value={query} onValueChange={onQuery} placeholder={placeholder} />
                    <CommandList>
                        {loading && (
                            <div className="flex items-center gap-2 px-3 py-2 text-xs text-muted-foreground">
                                <LoaderCircle className="size-3.5 animate-spin" /> Mencari…
                            </div>
                        )}
                        {children}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}

/**
 * Tandai dosen IAIA lain yang ikut menulis. Karya tercatat sekali dan muncul di daftar dosen yang ditandai.
 */
export function LecturerTagInput({ value, onChange, excludeId, invalid }: { value: TaggedLecturer[]; onChange: (value: TaggedLecturer[]) => void; excludeId?: number | null; invalid?: boolean }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const { items, loading } = usePeopleSearch('dosen', query, open);
    const taken = new Set([...value.map((lecturer) => lecturer.id), excludeId ?? 0]);
    const results = items.filter((person) => !taken.has(person.id));

    return (
        <div className="flex flex-wrap items-center gap-1.5">
            {value.map((lecturer) => (
                <Chip key={lecturer.id} icon={UserRound} label={lecturer.name} sub={lecturer.identifier} onRemove={() => onChange(value.filter((item) => item.id !== lecturer.id))} />
            ))}
            <PickerShell label="Tandai dosen" open={open} onOpenChange={setOpen} query={query} onQuery={setQuery} placeholder="Cari nama, NIDN, atau NUPTK…" loading={loading} invalid={invalid}>
                {!loading && <CommandEmpty>Dosen tidak ditemukan.</CommandEmpty>}
                <CommandGroup>
                    {results.map((person) => (
                        <CommandItem
                            key={person.id}
                            value={String(person.id)}
                            onSelect={() => {
                                onChange([...value, { id: person.id, name: person.name, identifier: person.number, study_program: person.study_program }]);
                                setOpen(false);
                                setQuery('');
                            }}
                        >
                            <PersonLine person={person} />
                        </CommandItem>
                    ))}
                </CommandGroup>
            </PickerShell>
        </div>
    );
}

/**
 * Tandai mahasiswa yang ikut menulis: dari data mahasiswa, atau ketik nama bila belum terdaftar (mis. alumni).
 */
export function StudentTagInput({ value, onChange, invalid }: { value: TaggedStudent[]; onChange: (value: TaggedStudent[]) => void; invalid?: boolean }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const { items, loading } = usePeopleSearch('mahasiswa', query, open);
    const taken = new Set(value.map((student) => student.student_id).filter(Boolean));
    const results = items.filter((person) => !taken.has(person.id));
    const manual = parseManual(query);

    const add = (student: TaggedStudent) => {
        onChange([...value, student]);
        setOpen(false);
        setQuery('');
    };

    return (
        <div className="flex flex-wrap items-center gap-1.5">
            {value.map((student, index) => (
                <Chip
                    key={student.student_id ?? `manual-${index}`}
                    icon={GraduationCap}
                    label={student.name}
                    sub={student.nim ?? (student.student_id ? null : 'di luar data')}
                    onRemove={() => onChange(value.filter((_, i) => i !== index))}
                />
            ))}
            <PickerShell label="Tandai mahasiswa" open={open} onOpenChange={setOpen} query={query} onQuery={setQuery} placeholder="Cari nama atau NIM…" loading={loading} invalid={invalid}>
                {results.length > 0 && (
                    <CommandGroup heading="Data mahasiswa">
                        {results.map((person) => (
                            <CommandItem key={person.id} value={String(person.id)} onSelect={() => add({ student_id: person.id, name: person.name, nim: person.number, study_program: person.study_program })}>
                                <PersonLine person={person} />
                            </CommandItem>
                        ))}
                    </CommandGroup>
                )}
                {manual ? (
                    <CommandGroup heading="Belum terdaftar?">
                        <CommandItem value="manual" onSelect={() => add({ student_id: null, name: manual.name, nim: manual.nim })}>
                            <UserPlus className="size-4 text-primary" />
                            <span className="min-w-0">
                                Tambah <b>{manual.name}</b>
                                {manual.nim && <span className="text-muted-foreground"> ({manual.nim})</span>} sebagai mahasiswa di luar data
                            </span>
                        </CommandItem>
                    </CommandGroup>
                ) : (
                    !loading &&
                    results.length === 0 && <div className="px-3 py-3 text-xs text-muted-foreground">Ketik nama atau NIM. Bila belum terdaftar (mis. alumni), ketik nama lengkap — boleh dengan NIM dalam kurung.</div>
                )}
            </PickerShell>
        </div>
    );
}

function PersonLine({ person }: { person: Person }) {
    return (
        <span className="min-w-0">
            <span className="block truncate font-medium">{person.name}</span>
            <span className="block truncate text-xs text-muted-foreground">
                {[person.number, person.study_program].filter(Boolean).join(' · ') || '—'}
            </span>
        </span>
    );
}

/** "Nama Lengkap (2511001)" → nama + NIM. Nama minimal 3 huruf. */
function parseManual(query: string): { name: string; nim: string | null } | null {
    const text = query.trim();
    const match = text.match(/^(.*?)\s*\((\w+)\)$/);
    const name = (match ? match[1] : text).trim();

    if (name.length < 3 || /^\d+$/.test(name)) return null;

    return { name, nim: match ? match[2] : null };
}
