import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { useCan } from '@/hooks/use-can';
import { type GuideArticle, type GuideViewer, guideArticles, isVisibleTo } from '@/lib/guide';

/**
 * Panduan yang relevan untuk pengguna aktif.
 * `showAll` dipakai admin yang perlu membantu peran lain (mis. LPM menjawab pertanyaan dosen).
 */
export function useGuideArticles(showAll = false): { articles: GuideArticle[]; viewer: GuideViewer; canShowAll: boolean } {
    const can = useCan();
    const user = usePage().props.auth.user;

    const viewer = useMemo<GuideViewer>(
        () => ({ can, isGuest: !user, isLecturer: !!user?.is_lecturer, isStudent: !!user?.is_student }),
        // `can` dibuat ulang setiap render; cukup bergantung pada pengguna.
        [user],
    );
    const canShowAll = !!user && can('users.manage');

    const articles = useMemo(
        () => (showAll && canShowAll ? guideArticles : guideArticles.filter((article) => isVisibleTo(article, viewer))),
        [showAll, canShowAll, viewer],
    );

    return { articles, viewer, canShowAll };
}

export { guideUrl } from '@/lib/guide/url';
