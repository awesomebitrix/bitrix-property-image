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
<?php
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
ImageProperty::subscribeToAll($eventMgr); // включает в себя subscribeToBuildList и subscribeToSetProperty
/*
 * Если свойство еще не создано или новое, то можно подисаться только на одно событие - сократит обращения к базе,
 * но старые изображения не будут менять размер после сохранения
 */
ImageProperty::subscribeToBuildList($eventMgr);
```

# Использование #

* Значением свойства нужно указывать массив как для обычного файла.
* Если происходит сохранение после измнения типа с "Файл" на "Изображение", то нужно сборить кэш сайта после всех сохранений.