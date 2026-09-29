<?php

namespace App\Http\Requests\Concerns;

/**
 * Message for the `uploaded` rule — the failure Laravel reports when PHP
 * itself rejected the upload before any validation rule could run
 * (spec-limite-pieces-jointes). Shared by every FormRequest accepting a
 * `file` so a size rejection (over upload_max_filesize) reads like the
 * 20MB rule's own message, while any other upload error (partial upload,
 * server-side temp/disk failure) is never misreported as a size problem.
 */
trait DescribesUploadFailure
{
    protected function uploadFailureMessage(string $acceptedFormats): string
    {
        $error = $this->file('file')?->getError();

        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return "Fichier trop volumineux (20 Mo maximum). {$acceptedFormats}";
        }

        return 'L\'envoi du fichier a échoué, merci de réessayer.';
    }
}
