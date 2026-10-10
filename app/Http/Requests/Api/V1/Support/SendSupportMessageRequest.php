<?php

namespace App\Http\Requests\Api\V1\Support;

use Illuminate\Validation\Validator;

/** Accept one atomic message containing text, up to six images, or both. */
class SendSupportMessageRequest extends SupportChatRequest
{
    /** Trim text so a whitespace-only request cannot create an empty message. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('message'))) {
            $this->merge(['message' => trim($this->input('message'))]);
        }
    }

    /** Bound multipart images and require a stable client UUID for safe retries. */
    public function rules(): array
    {
        return [
            'ticket_number' => ['required', 'string', 'max:100'],
            'client_message_id' => ['required', 'uuid'],
            'message' => ['nullable', 'string', 'max:5000', 'required_without:images'],
            'images' => ['nullable', 'array', 'min:1', 'max:6', 'required_without:message'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
        ];
    }

    /** Keep aggregate multipart uploads below twenty MiB. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $images = $this->file('images', []);
            if (is_array($images)) {
                $size = array_sum(array_map(fn ($file) => $file?->getSize() ?: 0, $images));
                if ($size > 20 * 1024 * 1024) {
                    $validator->errors()->add('images', 'Images must total no more than 20 MiB.');
                }
            }
        });
    }
}
