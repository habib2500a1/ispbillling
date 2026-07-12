<?php

namespace App\Http\Controllers;

use Intervention\Image\Laravel\Facades\Image;

class AvatarController extends Controller
{
    public function generateAvatar($name)
    {
        $fileName = 'images/avatars/'.md5($name).'.png';
        $fullPath = public_path($fileName);

        if (is_file($fullPath)) {
            return asset($fileName);
        }

        $initials = collect(explode(' ', $name))
            ->filter()
            ->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))
            ->join('');

        if ($initials === '') {
            $initials = '?';
        }

        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $image = Image::create(500, 500)->fill('#cef2ef')
            ->text($initials, 250, 250, function ($font) {
                $font->file(public_path('webfonts/Tinos-Regular.ttf'));
                $font->size(230);
                $font->color('#009e5c');
                $font->stroke('#cef2ef', 1);
                $font->align('center');
                $font->valign('middle');
                $font->lineHeight(1.6);
                $font->angle(0);
                $font->wrap(250);
            });

        $image->save($fullPath);
        @chmod($fullPath, 0664);

        return asset($fileName);
    }
}
