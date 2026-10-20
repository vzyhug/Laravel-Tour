<?php

namespace Database\Factories;

use App\Models\Tour;
use App\Models\TourSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TourSchedule> */
class TourScheduleFactory extends Factory
{
    protected $model = TourSchedule::class;

    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'day_number' => 1,
            'title' => 'Đón khách – Khám phá địa phương',
            'activities' => 'Đón khách tại điểm hẹn, tham quan các điểm nổi bật, thưởng thức đặc sản và nhận phòng nghỉ ngơi.',
            'has_breakfast' => false,
            'has_lunch' => true,
            'has_dinner' => true,
        ];
    }
}
