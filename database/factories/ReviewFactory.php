<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Review;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Review> */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'tour_id' => Tour::factory(),
            'rating' => fake()->numberBetween(3, 5),
            'feedback' => fake()->randomElement([
                'Hướng dẫn viên nhiệt tình, xe đón đúng giờ. Gia đình tôi rất thích các bữa ăn địa phương.',
                'Cảnh đẹp và lịch trình vừa sức. Tôi muốn có thêm thời gian tự do vào buổi chiều.',
                'Phòng sạch, dịch vụ đúng mô tả. Các điểm tham quan rất đáng trải nghiệm.',
                'Chuyến đi thuận lợi, hướng dẫn viên hỗ trợ chụp ảnh và giới thiệu văn hóa địa phương rất hay.',
            ]),
        ];
    }
}
