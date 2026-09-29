<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescribesUploadFailure;
use App\Models\Document;
use App\Models\DocumentAttachment;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class AttachDocumentFileRequest extends FormRequest
{
    use DescribesUploadFailure;

    /**
     * No authentication/authorization exists in v1 (NFR3) — always allowed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Same accepted formats/size limit as ImportDocumentRequest
            // (Code Map, spec-3-3) — an attachment is the same kind of
            // source file a document can otherwise only be imported as.
            'file' => [
                'required',
                File::types(['pdf', 'docx', 'xlsx'])->max(20 * 1024),
                $this->attachmentLimitRule(),
            ],
        ];
    }

    /**
     * Rejects the upload once the document already carries
     * DocumentAttachment::MAX_PER_DOCUMENT attachments. No lock is taken:
     * v1 is single-user and AttachmentsPanel never sends two uploads at
     * once (spec-limite-pieces-jointes, Never).
     */
    private function attachmentLimitRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $document = $this->route('document');

            if ($document instanceof Document && $document->attachments()->count() >= DocumentAttachment::MAX_PER_DOCUMENT) {
                $fail(DocumentAttachment::MAX_PER_DOCUMENT.' pièces jointes maximum par document.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $acceptedFormats = 'Formats acceptés : PDF, Word (.docx), Excel (.xlsx).';

        return [
            'file.required' => "Merci de sélectionner un fichier à joindre. {$acceptedFormats}",
            'file.mimes' => "Format non supporté. {$acceptedFormats}",
            'file.max' => "Fichier trop volumineux (20 Mo maximum). {$acceptedFormats}",
            'file.uploaded' => $this->uploadFailureMessage($acceptedFormats),
        ];
    }
}
