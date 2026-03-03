<?php

namespace App\Services;

use App\Models\Service;

class DevServiceRoleResolver
{
    /**
     * Dev-phase resolver.
     * Replace later with a real service_requirements table.
     */
    public function requiredRoleFor(Service $service): string
    {
        $name = mb_strtolower($service->name);

        if (str_contains($name, 'nail')) return 'beautician';
        if (str_contains($name, 'inject')) return 'nurse';

        // default doctor for consult/facial/detox/weight etc.
        return 'doctor';
    }
}
