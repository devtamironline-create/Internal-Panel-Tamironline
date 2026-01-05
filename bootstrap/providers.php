<?php

return [
    App\Providers\AppServiceProvider::class,
    Modules\SMS\Providers\SMSServiceProvider::class,
    Modules\Customer\Providers\CustomerServiceProvider::class,
    Modules\Product\Providers\ProductServiceProvider::class,
    Modules\Service\Providers\ServiceServiceProvider::class,
    Modules\Invoice\Providers\InvoiceServiceProvider::class,
    Modules\Ticket\Providers\TicketServiceProvider::class,
];
