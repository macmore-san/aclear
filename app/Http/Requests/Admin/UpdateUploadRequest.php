<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use ZipArchive;

/**
 * Gate for the Updates page upload.
 *
 * This runs before anything is staged or launched, and it is the only thing
 * standing between a mistaken file and an install that gets a foreign archive
 * extracted over it. Everything here fails closed.
 */
class UpdateUploadRequest extends FormRequest
{
    /** Files a genuine build-release.ps1 zip always contains. */
    private const REQUIRED_ENTRIES = ['VERSION', 'release-manifest.sha256', 'artisan', 'composer.json'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 512MB ceiling: a real release is ~60MB, so this only catches absurdity.
            // php.ini's upload_max_filesize is the real limit (README step 3 sets it).
            'release' => ['required', 'file', 'max:524288'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $file = $this->file('release');

            if (! $file || ! $file->isValid()) {
                return;
            }

            if (strtolower((string) $file->getClientOriginalExtension()) !== 'zip') {
                $validator->errors()->add('release', 'That is not a .zip file.');

                return;
            }

            $zip = new ZipArchive;

            if ($zip->open($file->getPathname()) !== true) {
                $validator->errors()->add('release', 'That file is not a readable zip archive.');

                return;
            }

            try {
                foreach (self::REQUIRED_ENTRIES as $entry) {
                    if ($zip->locateName($entry) === false) {
                        $validator->errors()->add('release', "This does not look like an AClear release — {$entry} is missing.");

                        return;
                    }
                }

                $uploaded = trim((string) $zip->getFromName('VERSION'));
                $current = (string) config('app.version');

                if ($uploaded === '' || preg_match('/^\d+\.\d+\.\d+/', $uploaded) !== 1) {
                    $validator->errors()->add('release', 'The release has no readable version number.');

                    return;
                }

                // A dev checkout has no VERSION file. Refuse rather than guess: applying a
                // release over a working tree would replace source with built output.
                if ($current === 'dev') {
                    $validator->errors()->add('release', 'This install has no version (a dev build). Updates can only be applied to a released install.');

                    return;
                }

                if (version_compare($uploaded, $current, '<=')) {
                    $validator->errors()->add('release', "This install is already on {$current}. The uploaded release is {$uploaded}.");

                    return;
                }

                $this->merge(['resolved_version' => $uploaded]);
            } finally {
                $zip->close();
            }
        });
    }
}
