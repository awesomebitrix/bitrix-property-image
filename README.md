# Установка #

В компоновщик **composer.json** нужно добавить

```
#!json
{
// ...
    "require": {
        "devel59/bitrix-property-image": "dev-master"
    }
// ...
}
```
В файле **bitrix/.settings.php** нужно указать расширение для работы с изображениями

```
#!php
// ...
    'image_property' => array(
        'value' => array(
            'imagine_extension' => 'imagick' // imagick, gd, gmagick
        )
    )
// ...
```
В файл **bitrix/php_interface/init.php** нужно добавить

```
#!php
<?php
use Bitrix\Main\EventManager;
use Devel59\Bitrix\Iblock\Property\ImageProperty;

require_once __DIR__ . '<путь до корня проекта>/vendor/autoload.php';

$eventMgr = EventManager::getInstance();
ImageProperty::subscribeToEvents($eventMgr);
```

# Использование #

Значением свойства нужно указывать массив как для обычного файла.