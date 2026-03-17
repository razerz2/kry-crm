<?php

namespace Webkul\Activity\Providers;

use Webkul\Activity\Contracts\Activity as ActivityContract;
use Webkul\Activity\Contracts\File as FileContract;
use Webkul\Activity\Contracts\Participant as ParticipantContract;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Models\File;
use Webkul\Activity\Models\Participant;
use Webkul\Core\Providers\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        Activity::class,
        File::class,
        Participant::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        parent::register();

        // Fallback bindings keep module repositories resolvable in minimal CI setups.
        $this->app->bindIf(ActivityContract::class, Activity::class);
        $this->app->bindIf(FileContract::class, File::class);
        $this->app->bindIf(ParticipantContract::class, Participant::class);
    }
}
