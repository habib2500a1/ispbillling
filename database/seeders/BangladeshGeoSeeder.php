<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Upazila;
use Illuminate\Database\Seeder;

class BangladeshGeoSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const EXTRA_UPAZILAS = [
        'Dhaka' => ['Uttara', 'Mirpur', 'Mohammadpur', 'Dhanmondi', 'Gulshan', 'Tejgaon', 'Demra', 'Keraniganj'],
        'Gazipur' => ['Tongi', 'Kaliakair', 'Kapasia', 'Sreepur'],
        'Narayanganj' => ['Bandar', 'Rupganj', 'Sonargaon', 'Araihazar'],
        'Chattogram' => ['Kotwali', 'Panchlaish', 'Halishahar', 'Patiya', 'Sitakunda'],
        'Sylhet' => ['Zindabazar', 'Beanibazar', 'Golapganj', 'Companiganj'],
        'Rajshahi' => ['Boalia', 'Paba', 'Godagari', 'Tanore'],
        'Khulna' => ['Khalishpur', 'Daulatpur', 'Sonadanga', 'Dighalia'],
        'Barishal' => ['Kotwali', 'Babuganj', 'Gournadi', 'Muladi'],
        'Rangpur' => ['Kotwali', 'Badarganj', 'Pirgachha', 'Kaunia'],
        'Mymensingh' => ['Kotwali', 'Trishal', 'Muktagachha', 'Gafargaon'],
    ];

    public function run(): void
    {
        foreach ($this->districts() as $row) {
            $district = District::query()->updateOrCreate(
                ['name' => $row['name']],
                [
                    'code' => $row['code'],
                    'division' => $row['division'],
                    'is_active' => true,
                ],
            );

            $upazilas = self::EXTRA_UPAZILAS[$row['name']] ?? [$row['name'].' Sadar'];
            foreach ($upazilas as $upazilaName) {
                Upazila::query()->updateOrCreate(
                    [
                        'district_id' => $district->id,
                        'name' => $upazilaName,
                    ],
                    [
                        'code' => null,
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    /** @return list<array{name: string, code: string, division: string}> */
    private function districts(): array
    {
        return [
            ['name' => 'Bagerhat', 'code' => 'BGH', 'division' => 'Khulna'],
            ['name' => 'Bandarban', 'code' => 'BDB', 'division' => 'Chattogram'],
            ['name' => 'Barguna', 'code' => 'BRG', 'division' => 'Barishal'],
            ['name' => 'Barishal', 'code' => 'BAR', 'division' => 'Barishal'],
            ['name' => 'Bhola', 'code' => 'BHO', 'division' => 'Barishal'],
            ['name' => 'Bogura', 'code' => 'BOG', 'division' => 'Rajshahi'],
            ['name' => 'Brahmanbaria', 'code' => 'BRB', 'division' => 'Chattogram'],
            ['name' => 'Chandpur', 'code' => 'CHP', 'division' => 'Chattogram'],
            ['name' => 'Chattogram', 'code' => 'CTG', 'division' => 'Chattogram'],
            ['name' => 'Chuadanga', 'code' => 'CHU', 'division' => 'Khulna'],
            ['name' => "Cox's Bazar", 'code' => 'CXB', 'division' => 'Chattogram'],
            ['name' => 'Cumilla', 'code' => 'CUM', 'division' => 'Chattogram'],
            ['name' => 'Dhaka', 'code' => 'DHK', 'division' => 'Dhaka'],
            ['name' => 'Dinajpur', 'code' => 'DIN', 'division' => 'Rangpur'],
            ['name' => 'Faridpur', 'code' => 'FAR', 'division' => 'Dhaka'],
            ['name' => 'Feni', 'code' => 'FEN', 'division' => 'Chattogram'],
            ['name' => 'Gaibandha', 'code' => 'GAI', 'division' => 'Rangpur'],
            ['name' => 'Gazipur', 'code' => 'GAZ', 'division' => 'Dhaka'],
            ['name' => 'Gopalganj', 'code' => 'GOP', 'division' => 'Dhaka'],
            ['name' => 'Habiganj', 'code' => 'HAB', 'division' => 'Sylhet'],
            ['name' => 'Jamalpur', 'code' => 'JAM', 'division' => 'Mymensingh'],
            ['name' => 'Jashore', 'code' => 'JES', 'division' => 'Khulna'],
            ['name' => 'Jhalokati', 'code' => 'JHA', 'division' => 'Barishal'],
            ['name' => 'Jhenaidah', 'code' => 'JHE', 'division' => 'Khulna'],
            ['name' => 'Joypurhat', 'code' => 'JOY', 'division' => 'Rajshahi'],
            ['name' => 'Khagrachhari', 'code' => 'KHA', 'division' => 'Chattogram'],
            ['name' => 'Khulna', 'code' => 'KHU', 'division' => 'Khulna'],
            ['name' => 'Kishoreganj', 'code' => 'KIS', 'division' => 'Dhaka'],
            ['name' => 'Kurigram', 'code' => 'KUR', 'division' => 'Rangpur'],
            ['name' => 'Kushtia', 'code' => 'KUS', 'division' => 'Khulna'],
            ['name' => 'Lakshmipur', 'code' => 'LAK', 'division' => 'Chattogram'],
            ['name' => 'Lalmonirhat', 'code' => 'LAL', 'division' => 'Rangpur'],
            ['name' => 'Madaripur', 'code' => 'MAD', 'division' => 'Dhaka'],
            ['name' => 'Magura', 'code' => 'MAG', 'division' => 'Khulna'],
            ['name' => 'Manikganj', 'code' => 'MAN', 'division' => 'Dhaka'],
            ['name' => 'Meherpur', 'code' => 'MEH', 'division' => 'Khulna'],
            ['name' => 'Moulvibazar', 'code' => 'MOU', 'division' => 'Sylhet'],
            ['name' => 'Munshiganj', 'code' => 'MUN', 'division' => 'Dhaka'],
            ['name' => 'Mymensingh', 'code' => 'MYM', 'division' => 'Mymensingh'],
            ['name' => 'Naogaon', 'code' => 'NAO', 'division' => 'Rajshahi'],
            ['name' => 'Narail', 'code' => 'NAR', 'division' => 'Khulna'],
            ['name' => 'Narayanganj', 'code' => 'NAY', 'division' => 'Dhaka'],
            ['name' => 'Narsingdi', 'code' => 'NAS', 'division' => 'Dhaka'],
            ['name' => 'Natore', 'code' => 'NAT', 'division' => 'Rajshahi'],
            ['name' => 'Netrokona', 'code' => 'NET', 'division' => 'Mymensingh'],
            ['name' => 'Nilphamari', 'code' => 'NIL', 'division' => 'Rangpur'],
            ['name' => 'Noakhali', 'code' => 'NOA', 'division' => 'Chattogram'],
            ['name' => 'Pabna', 'code' => 'PAB', 'division' => 'Rajshahi'],
            ['name' => 'Panchagarh', 'code' => 'PAN', 'division' => 'Rangpur'],
            ['name' => 'Patuakhali', 'code' => 'PAT', 'division' => 'Barishal'],
            ['name' => 'Pirojpur', 'code' => 'PIR', 'division' => 'Barishal'],
            ['name' => 'Rajbari', 'code' => 'RAJ', 'division' => 'Dhaka'],
            ['name' => 'Rajshahi', 'code' => 'RAJ', 'division' => 'Rajshahi'],
            ['name' => 'Rangamati', 'code' => 'RAN', 'division' => 'Chattogram'],
            ['name' => 'Rangpur', 'code' => 'RGP', 'division' => 'Rangpur'],
            ['name' => 'Satkhira', 'code' => 'SAT', 'division' => 'Khulna'],
            ['name' => 'Shariatpur', 'code' => 'SHA', 'division' => 'Dhaka'],
            ['name' => 'Sherpur', 'code' => 'SHE', 'division' => 'Mymensingh'],
            ['name' => 'Sirajganj', 'code' => 'SIR', 'division' => 'Rajshahi'],
            ['name' => 'Sunamganj', 'code' => 'SUN', 'division' => 'Sylhet'],
            ['name' => 'Sylhet', 'code' => 'SYL', 'division' => 'Sylhet'],
            ['name' => 'Tangail', 'code' => 'TAN', 'division' => 'Dhaka'],
            ['name' => 'Thakurgaon', 'code' => 'THA', 'division' => 'Rangpur'],
        ];
    }
}
