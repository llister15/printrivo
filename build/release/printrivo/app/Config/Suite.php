<?php
namespace Config;
use CodeIgniter\Config\BaseConfig;
class Suite extends BaseConfig
{
    // Add future protected apps here to include them in both the dashboard and launcher.
    // Their routes must still enforce the member:subscription filter server-side.
    public array $apps = [
        ['label'=>'LAY OUT', 'name'=>'Printrivo Gang Sheet Builder', 'shortName'=>'Gang Sheet Builder', 'icon'=>'GS',
            'description'=>'Build fitted 16- or 22-inch DTF or UVDTF gang sheets, arrange artwork, and download print-ready PNGs.',
            'route'=>'tools/gang-sheet', 'action'=>'Open Builder', 'preview'=>'Printrivo Gang Sheet Builder.png'],
        ['label'=>'PREPARE', 'name'=>'Printrivo Image Studio', 'shortName'=>'Image Studio', 'icon'=>'IS',
            'description'=>'Edit an image, remove backgrounds, add text, adjust colors, and export print-ready PNGs.',
            'route'=>'tools/image-studio', 'action'=>'Open Studio', 'preview'=>'image-studio-preview.png'],
        ['label'=>'CONNECT', 'name'=>'Printrivo QR Generator', 'shortName'=>'QR Generator', 'icon'=>'QR',
            'description'=>'Create QR codes with optional icons or custom logos.',
            'route'=>'tools/qr', 'action'=>'Create QR', 'preview'=>'Printrivo QR Generator.png'],
    ];
}
