<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class InternalLayout extends Component
{
    public ?string $title;
    public ?string $subtitle;

    public function __construct(?string $title = null, ?string $subtitle = null)
    {
        $this->title = $title;
        $this->subtitle = $subtitle;
    }

    public function render(): View
    {
        return view('layouts.internal');
    }
}