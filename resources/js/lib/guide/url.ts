/** URL Pusat Panduan. Modul ringan: tidak memuat isi artikel. */
export function guideUrl(params: { q?: string; kategori?: string; artikel?: string; semua?: boolean } = {}) {
    const query: Record<string, string> = {};
    if (params.q) query.q = params.q;
    if (params.kategori) query.kategori = params.kategori;
    if (params.artikel) query.artikel = params.artikel;
    if (params.semua) query.semua = '1';
    return route('guide.index', query);
}
