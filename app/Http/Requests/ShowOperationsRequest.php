<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ActivityMetric;
use App\Enums\ActivityScale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ShowOperationsRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->isAdmin() ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', Rule::date()->format('Y-m-d')->todayOrBefore()],
            'to' => ['nullable', Rule::date()->format('Y-m-d')->todayOrBefore(), Rule::when($this->filled('from'), 'after_or_equal:from')],
            'scale' => ['nullable', Rule::enum(ActivityScale::class)],
            'metric' => ['nullable', Rule::enum(ActivityMetric::class)],
        ];
    }

    /**
     * The activity window, the last seven days by default, like the volume table.
     */
    public function activityScale(): ActivityScale
    {
        return $this->enum('scale', ActivityScale::class) ?? ActivityScale::Week;
    }

    /**
     * The series the activity graph draws, commits by default.
     */
    public function activityMetric(): ActivityMetric
    {
        return $this->enum('metric', ActivityMetric::class) ?? ActivityMetric::Commits;
    }

    /**
     * The traffic range, the last seven days by default.
     */
    public function trafficFrom(): CarbonImmutable
    {
        return $this->date('from', 'Y-m-d')?->toImmutable() ?? today()->subDays(6)->toImmutable();
    }

    public function trafficTo(): CarbonImmutable
    {
        return $this->date('to', 'Y-m-d')?->toImmutable() ?? today()->toImmutable();
    }
}
