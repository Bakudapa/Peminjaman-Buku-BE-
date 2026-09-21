<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $book = $this->route('book');

        return [
            'title'         => 'sometimes|string',
            'author'        => 'sometimes|string',
            'category'      => 'sometimes|string',
            'description'   => 'sometimes|string',
            'total_copies'  => [
                'sometimes',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) use ($book) {
                    $activeLoans = $book->loans()->where('status', 'active')->count();

                    if ($value < $activeLoans) {
                        $fail("Total copies tidak boleh kurang dari {$activeLoans} (jumlah buku yang sedang dipinjam).");
                    }
                },
            ],
        ];
    }
}