<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Inertia\Inertia;

abstract class Controller
{
    /**
     * Kirim notifikasi toast sekali tampil ke frontend.
     */
    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }

    /**
     * Hentikan proses dan kembali ke halaman sebelumnya dengan pesan kesalahan.
     */
    protected function failWith(string $message): never
    {
        $this->toast($message, 'error');

        throw new HttpResponseException(back());
    }

    /**
     * Hapus data; bila masih dirujuk data lain, tampilkan pesan yang ramah.
     */
    protected function deleteSafely(Model $model, string $label): bool
    {
        try {
            $model->delete();
            $this->toast("{$label} berhasil dihapus.");

            return true;
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $this->toast("{$label} tidak dapat dihapus karena masih digunakan oleh data lain. Nonaktifkan saja bila tidak dipakai.", 'error');

            return false;
        }
    }

    /**
     * Pastikan pengguna berwenang atas prodi terkait (hierarchical access).
     */
    protected function authorizeStudyProgram(Request $request, ?int $studyProgramId): void
    {
        abort_if(
            $studyProgramId !== null && ! $request->user()->canAccessStudyProgram($studyProgramId),
            403,
            'Data ini berada di luar cakupan akses Anda.',
        );
    }

    /**
     * Ringkasan filter yang dikembalikan ke frontend.
     *
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    protected function filters(Request $request, array $keys): array
    {
        return collect(['search', 'sort', ...$keys])
            ->mapWithKeys(fn (string $key): array => [$key => $request->input($key)])
            ->all();
    }
}
