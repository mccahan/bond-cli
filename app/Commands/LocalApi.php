<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\Http;

class LocalApi extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'local-api {ip} {token}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $ip = $this->argument('ip');
        $token = $this->argument('token');

        Http::macro('bond', function() use($token, $ip) {
            return Http::withHeaders([
               'BOND-Token' => $token ,
            ])->baseUrl('http://' . $ip . '/v2/');
        });

        // Collect all the devices together, even though we only need the names right now
        $response = Http::bond()->get('devices')->json();
        $deviceIds = collect($response)->except(['_','__'])->keys();
        $devices = $deviceIds->mapWithKeys(function($item, $key){
            $response = Http::bond()->get('devices/' . $item);
            return [$item => $response->json()];
        });

        $deviceNames = $devices->mapWithKeys(function($item, $key){
            return [$key => $item['name']];
        });

        // Pick a device from the menu
        $deviceId = $this->menu('Select a device', $deviceNames->all())->open();
        $device = $devices->get($deviceId);

        $response = Http::bond()->get('devices/' . $deviceId . '/commands')->json();
        $commandIds = collect($response)->except(['_','__'])->keys();
        $commands = $commandIds->mapWithKeys(function($item, $key) use($deviceId) {
            $response = Http::bond()->get('devices/' . $deviceId . '/commands/' . $item)->json();
            $response['key'] = $item;
            return [$item => $response];
        });

        $menu = $this->menu('Commands');
        $commands->groupBy('category_name')->map(function($item, $key) use($menu){
            $menu->addStaticItem($key);
            $item->sortBy('name')->map(function($item, $key) use($menu){
                $menu->addOption($item['key'], $item['name']);
            });
            $menu->addLineBreak();
        });
        $commandId = $menu->open();

        $this->info("Transmitting command...");
        $result = Http::bond()->withBody('{}')->put('devices/' . $deviceId . '/commands/' . $commandId . '/tx');
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
