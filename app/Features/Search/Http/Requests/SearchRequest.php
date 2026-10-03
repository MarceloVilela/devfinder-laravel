<?php

declare(strict_types=1);

namespace App\Features\Search\Http\Requests;

use App\Features\Search\Data\SearchData;
use Illuminate\Foundation\Http\FormRequest;

final class SearchRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['q' => ['required', 'string', 'max:100']];
    }

    public function searchData(): SearchData
    {
        $term = $this->validated('q');

        return new SearchData(is_string($term) ? $term : '');
    }
}
