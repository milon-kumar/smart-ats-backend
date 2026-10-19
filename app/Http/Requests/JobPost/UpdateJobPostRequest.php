<?php

namespace App\Http\Requests\JobPost;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateJobPostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'recruiter';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'employment_type' => ['sometimes', 'required', 'string', 'in:full_time,part_time,remote,internship'],
            'location' => ['sometimes', 'required', 'string', 'max:255'],
            'salary_min' => ['nullable', 'numeric', 'min:0'],
            'salary_max' => ['nullable', 'numeric', 'gte:salary_min'],
            'experience_years' => ['sometimes', 'required', 'integer', 'min:0'],
            'deadline' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'required', 'string', 'in:open,closed'],
            'required_skills' => ['sometimes', 'required', 'array', 'min:1'],
            'required_skills.*' => ['string'],
        ];
    }
}
