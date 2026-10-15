import type { GuideArticle } from './types';

/**
 * Pencarian panduan berbasis kata kunci, dibuat untuk pertanyaan sehari-hari
 * seperti "dimana user?", "gimana reset password mahasiswa", atau "upload rps".
 *
 * - Frasa dua kata dilebur dulu (mis. "kata sandi" → password).
 * - Sinonim dan istilah awam dipetakan ke satu kata dasar (user = pengguna = akun).
 * - Kata tanya/kata sambung diabaikan.
 * - Toleran salah ketik ringan dan awalan kata ("pengg" cocok dengan "pengguna").
 */

const PHRASES: [RegExp, string][] = [
    [/\bkata ?sandi\b/g, 'password'],
    [/\bpass ?word\b/g, 'password'],
    [/\bprogram studi\b/g, 'prodi'],
    [/\bhak akses\b/g, 'hakakses'],
    [/\brencana aksi\b/g, 'rencanaaksi'],
    [/\baction plan\b/g, 'rencanaaksi'],
    [/\btindak ?lanjut\b/g, 'tindaklanjut'],
    [/\bkarya ilmiah\b/g, 'karyailmiah'],
    [/\bmata ?kuliah\b/g, 'matakuliah'],
    [/\blog audit\b/g, 'logaudit'],
    [/\blog ?in\b/g, 'login'],
    [/\blog ?out\b/g, 'logout'],
    [/\bsign ?in\b/g, 'login'],
    [/\bsign ?out\b/g, 'logout'],
    [/\batur ulang\b/g, 'reset'],
    [/\bdaftar tilik\b/g, 'daftartilik'],
    [/\btahun akademik\b/g, 'periode'],
    [/\bdokumen bukti\b/g, 'dokumen'],
    [/\bban ?pt\b/g, 'akreditasi'],
    [/\bno\.? ?hp\b/g, 'hp'],
    [/\b(?:ber)?nama((?: \w+){0,2}?)(?: yang)? (?:sama|kembar|ganda|mirip)\b/g, 'namakembar$1'],
    [/\bbukan saya\b/g, 'bukansaya'],
    [/\bsalah (isi|pilih|input)\b/g, 'salahisi'],
    [/\btidak (bisa|dapat)\b/g, 'gagal'],
    [/\b(nggak|gak|ga|enggak|ndak) bisa\b/g, 'gagal'],
    [/\bctrl ?k\b/g, 'ctrlk'],
];

const SYNONYMS: string[][] = [
    ['pengguna', 'user', 'users', 'akun', 'account', 'operator'],
    ['password', 'sandi', 'pasword', 'passwd', 'pwd', 'pin'],
    ['login', 'masuk', 'logon'],
    ['logout', 'keluar'],
    ['dosen', 'lecturer', 'pengajar', 'pengampu'],
    ['mahasiswa', 'mhs', 'student', 'siswa'],
    ['prodi', 'jurusan', 'programstudi'],
    ['kelas', 'rombel'],
    ['monev', 'evaluasi', 'edom', 'kuesioner', 'kuisioner', 'angket', 'survei', 'survey', 'monitoring'],
    ['instrumen', 'instrument', 'formulir'],
    ['butir', 'pertanyaan', 'soal', 'item', 'pernyataan'],
    ['ami', 'audit'],
    ['auditor', 'asesor'],
    ['temuan', 'finding', 'ketidaksesuaian', 'ncr'],
    ['dokumen', 'bukti', 'berkas', 'file', 'lampiran'],
    ['unggah', 'upload', 'lampirkan', 'attach'],
    ['impor', 'import', 'massal'],
    ['excel', 'xlsx', 'csv', 'spreadsheet'],
    ['ekspor', 'export', 'unduh', 'download'],
    ['laporan', 'report', 'rekap', 'pdf', 'rapor', 'cetak', 'print'],
    ['hakakses', 'akses', 'izin', 'permission', 'role', 'roles', 'peran', 'wewenang', 'rbac'],
    ['cadangan', 'backup', 'bekap', 'cadangkan'],
    ['pulihkan', 'restore', 'pemulihan', 'rollback'],
    ['periode', 'semester'],
    ['akreditasi', 'lam', 'lamdik', 'lamgama', 'reakreditasi', 'visitasi'],
    ['karyailmiah', 'publikasi', 'jurnal', 'artikel', 'penelitian', 'riset', 'sinta', 'scopus', 'prosiding', 'hki', 'paper'],
    ['rekomendasi', 'saran'],
    ['rencanaaksi', 'tindaklanjut'],
    ['notifikasi', 'pemberitahuan', 'notif', 'lonceng'],
    ['tambah', 'buat', 'baru', 'add', 'create', 'input', 'daftarkan', 'bikin', 'membuat', 'menambah', 'menambahkan', 'tambahkan', 'masukkan'],
    ['ubah', 'edit', 'ganti', 'update', 'mengubah', 'mengganti', 'perbarui', 'memperbarui'],
    ['hapus', 'delete', 'menghapus', 'buang', 'remove'],
    ['nonaktif', 'nonaktifkan', 'blokir', 'disable', 'suspend', 'matikan'],
    ['aktifkan', 'enable', 'mengaktifkan'],
    ['skala', 'likert', 'klasifikasi', 'predikat'],
    ['logo', 'kop', 'lambang'],
    ['reset', 'resetting'],
    ['verifikasi', 'validasi', 'sahkan', 'approve', 'memverifikasi', 'valid'],
    ['terbit', 'terbitkan', 'publish', 'rilis', 'menerbitkan'],
    ['jadwal', 'schedule', 'agenda', 'jadwalkan'],
    ['pic', 'penanggungjawab'],
    ['angkatan', 'cohort'],
    ['dashboard', 'beranda', 'home'],
    ['profil', 'profile', 'biodata'],
    ['hp', 'wa', 'whatsapp', 'telepon', 'telp'],
    ['email', 'surel', 'mail'],
    ['peserta', 'krs'],
    ['matakuliah', 'mk', 'course'],
    ['fakultas', 'dekan'],
    ['gagal', 'error', 'masalah', 'kendala', 'eror'],
    ['lupa', 'hilang'],
    ['tutup', 'close', 'menutup'],
    ['anonim', 'rahasia', 'privasi'],
    ['cari', 'search', 'mencari', 'temukan'],
];

const STOPWORDS = new Set(
    (
        'di ke dari yang dan atau untuk cara bagaimana gimana gmn gmana mana dimana kemana apa apakah saya aku kami kita ini itu ' +
        'mau ingin pengen bisa dapat dengan pada dalam ada adalah tolong mohon menu halaman fitur aplikasi simutu sih kok ya dong deh ' +
        'nya the how to where is jika kalau kalo agar supaya biar sudah udah kah lah tuh nih caranya letak letaknya tempat ' +
        'lewat melalui sebuah suatu oleh juga lagi harus perlu sama'
    ).split(' '),
);

const canonical = new Map<string, string>();
for (const group of SYNONYMS) for (const word of group) canonical.set(word, group[0]);

const normalize = (text: string) => {
    let value = text
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[›>/_|•·,.;:!?()[\]"'“”‘’*-]+/g, ' ');
    for (const [pattern, replacement] of PHRASES) value = value.replace(pattern, replacement);
    return value;
};

/** Pangkas akhiran umum bahasa Indonesia agar "penggunanya" ≈ "pengguna". */
const stem = (word: string) => (word.length > 5 ? word.replace(/(nya|kan|lah|pun)$/, '') : word);

const PREFIXES = ['meng', 'mem', 'men', 'me', 'di', 'ber', 'ter'];

/** Kata dasar kanonis: "mengekspor" → ekspor, "diunggah" → unggah, "penggunanya" → pengguna. */
const toTerm = (word: string) => {
    const stemmed = stem(word);
    const direct = canonical.get(word) ?? canonical.get(stemmed);
    if (direct) return direct;
    for (const prefix of PREFIXES) {
        if (stemmed.length - prefix.length >= 4 && stemmed.startsWith(prefix)) {
            const root = canonical.get(stemmed.slice(prefix.length));
            if (root) return root;
        }
    }
    return stemmed;
};

export function tokenize(text: string): string[] {
    return normalize(text)
        .split(/\s+/)
        .filter((word) => word.length > 1 && !STOPWORDS.has(word))
        .map(toTerm);
}

/** Jarak edit ≤ 1 (salah ketik satu huruf). */
function withinOneEdit(a: string, b: string): boolean {
    if (Math.abs(a.length - b.length) > 1) return false;
    let i = 0;
    let j = 0;
    let edits = 0;
    while (i < a.length && j < b.length) {
        if (a[i] === b[j]) {
            i++;
            j++;
            continue;
        }
        if (++edits > 1) return false;
        if (a.length > b.length) i++;
        else if (b.length > a.length) j++;
        else {
            i++;
            j++;
        }
    }
    return edits + (a.length - i) + (b.length - j) <= 1;
}

const FIELD_WEIGHTS = { title: 8, keywords: 6, where: 4, summary: 3, body: 1 } as const;
type Field = keyof typeof FIELD_WEIGHTS;

interface IndexedArticle {
    article: GuideArticle;
    fields: Record<Field, Set<string>>;
}

const indexCache = new WeakMap<GuideArticle[], IndexedArticle[]>();

function buildIndex(articles: GuideArticle[]): IndexedArticle[] {
    const cached = indexCache.get(articles);
    if (cached) return cached;

    const index = articles.map((article) => ({
        article,
        fields: {
            title: new Set(tokenize(article.title)),
            keywords: new Set(tokenize((article.keywords ?? []).join(' '))),
            where: new Set(tokenize((article.where?.path ?? []).join(' '))),
            summary: new Set(tokenize(article.summary)),
            body: new Set(tokenize([...(article.steps ?? []), ...(article.notes ?? []), article.who ?? ''].join(' '))),
        },
    }));
    indexCache.set(articles, index);
    return index;
}

/** Jumlah bobot semua bagian panduan yang memuat kata ini (judul + kata kunci + lokasi menu + …). */
function termScore(term: string, fields: Record<Field, Set<string>>): number {
    let total = 0;
    for (const field of Object.keys(FIELD_WEIGHTS) as Field[]) {
        const weight = FIELD_WEIGHTS[field];
        const words = fields[field];
        if (words.has(term)) {
            total += weight;
            continue;
        }
        for (const word of words) {
            // Awalan: "pengg" → "pengguna"; salah ketik satu huruf untuk kata ≥ 5 huruf.
            if ((term.length >= 3 && word.startsWith(term)) || (term.length >= 5 && withinOneEdit(term, word))) {
                total += weight * 0.7;
                break;
            }
        }
    }
    return total;
}

export interface GuideSearchResult {
    article: GuideArticle;
    score: number;
}

export function searchGuide(articles: GuideArticle[], query: string, limit = 30): GuideSearchResult[] {
    const terms = [...new Set(tokenize(query))];
    if (terms.length === 0) return [];

    const scored = buildIndex(articles)
        .map(({ article, fields }) => {
            let matched = 0;
            let score = 0;
            for (const term of terms) {
                const value = termScore(term, fields);
                if (value > 0) {
                    matched++;
                    score += value;
                }
            }
            // Bonus bila semua kata kunci ada di judul: biasanya itulah panduan yang dicari.
            if (matched === terms.length && terms.every((term) => fields.title.has(term))) score += 10;
            return { article, matched, score };
        })
        .filter((result) => result.matched > 0);

    if (scored.length === 0) return [];

    // Utamakan panduan yang memuat paling banyak kata kunci; sisanya hanya bila selisihnya satu kata.
    const bestMatched = Math.max(...scored.map((result) => result.matched));
    const minimum = terms.length >= 3 ? bestMatched - 1 : bestMatched;

    const candidates = scored.filter((result) => result.matched >= Math.max(1, minimum));
    // Buang kecocokan yang hanya kebetulan muncul di badan teks panduan lain.
    const topScore = Math.max(...candidates.map((result) => result.score));

    return candidates
        .filter((result) => result.score >= topScore * 0.3)
        .sort((a, b) => b.matched - a.matched || b.score - a.score)
        .slice(0, limit)
        .map(({ article, score }) => ({ article, score }));
}
