<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescribesUploadFailure;
use App\Models\DocumentAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class ImportDocumentRequest extends FormRequest
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
     * `draft_token`: Inertia forms send a cleared/never-set value as an
     * empty string over the wire, so it's normalized back to null before
     * validation so `nullable` applies correctly.
     *
     * `tag_ids`/`draft_attachments`: neither the import page's TagSelector
     * nor AttachmentsPanel ever sends `null` in practice, only an array
     * (possibly empty) — normalized defensively anyway (Code review,
     * spec-3-1) so an explicit `null` from any other caller is treated the
     * same as "none" rather than failing the `array` rule.
     */
    protected function prepareForValidation(): void
    {
        if ($this->draft_token === '') {
            $this->merge(['draft_token' => null]);
        }

        if ($this->tag_ids === null) {
            $this->merge(['tag_ids' => []]);
        }

        if ($this->draft_attachments === null) {
            $this->merge(['draft_attachments' => []]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(['pdf', 'docx', 'xlsx'])->max(20 * 1024),
            ],
            // Prefilled client-side from the filename (minus its extension)
            // and editable; optional here so any caller omitting it falls
            // back to the original filename in ImportDocumentAction.
            'title' => ['nullable', 'string', 'max:255'],
            // An optional set of tags chosen on the import page's
            // TagSelector, assigned afterwards through
            // SyncDocumentTagsAction (Boundaries & Constraints, spec-3-1) —
            // never blocking, `tag_ids` may be absent or empty.
            'tag_ids' => ['array'],
            'tag_ids.*' => ['distinct', 'integer', Rule::exists('tags', 'id')],
            // Same rules as CreateDocumentRequest (spec-3-3): the import page
            // generates its draft token client-side, like the editor.
            // Required as soon as attachments are listed — without it they
            // would be silently dropped by the relocation step.
            'draft_token' => ['nullable', 'required_with:draft_attachments', 'uuid'],
            // `filename` is used directly to build a storage path in
            // RelocatesDraftAttachments::relocateDraftAttachments() — the
            // regex restricts it to exactly the `{uuid}.{ext}` shape
            // UploadDraftAttachmentAction ever generates.
            'draft_attachments' => ['array', 'max:'.DocumentAttachment::MAX_PER_DOCUMENT],
            'draft_attachments.*.filename' => [
                'required',
                'string',
                'regex:/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\.[a-zA-Z0-9]+$/',
            ],
            'draft_attachments.*.original_filename' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $acceptedFormats = 'Formats acceptés : PDF, Word (.docx), Excel (.xlsx).';

        return [
            'file.required' => "Merci de sélectionner un fichier à importer. {$acceptedFormats}",
            'file.mimes' => "Format non supporté. {$acceptedFormats}",
            'file.max' => "Fichier trop volumineux (20 Mo maximum). {$acceptedFormats}",
            'file.uploaded' => $this->uploadFailureMessage($acceptedFormats),
            'title.max' => 'Le titre est trop long (255 caractères maximum).',
            'tag_ids.array' => 'Tags invalides.',
            'tag_ids.*.integer' => 'Tag invalide.',
            'tag_ids.*.exists' => 'Tag invalide.',
            'draft_token.uuid' => 'Session d\'édition invalide, merci de recharger la page.',
            'draft_token.required_with' => 'Session d\'édition manquante pour les pièces jointes, merci de recharger la page.',
            'draft_attachments.array' => 'Pièces jointes invalides.',
            'draft_attachments.max' => DocumentAttachment::MAX_PER_DOCUMENT.' pièces jointes maximum par document.',
            'draft_attachments.*.filename.required' => 'Pièce jointe invalide.',
            'draft_attachments.*.filename.regex' => 'Pièce jointe invalide.',
            'draft_attachments.*.original_filename.required' => 'Pièce jointe invalide.',
            'draft_attachments.*.original_filename.max' => 'Nom de pièce jointe trop long (255 caractères maximum).',
        ];
    }
}
