<?php
namespace Devel59\Bitrix\Iblock\Property;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Configuration;
use Bitrix\Main\Data\ConnectionPool;
use Bitrix\Main\EventManager;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Point;

/**
 * Файл с изображением
 */
class ImageProperty {
    /**
     * Добавление обработчика к составлению списка свойств
     *
     * @param EventManager $eventManager
     * @return void
     */
    public static function subscribeToBuildList(EventManager $eventManager) {
        $calledClass = get_called_class();
        $eventManager->addEventHandler(
            'iblock',
            'OnIBlockPropertyBuildList',
            $calledClass . '::getUserTypeDescription',
            false,
            100
        );
    }

    /**
     * Добавление обработчика к установке значений свойства
     *
     * @param EventManager $eventManager
     * @return void
     */
    public static function subscribeToSetProperty(EventManager $eventManager) {
        $calledClass = get_called_class();
        $eventManager->addEventHandler(
            'iblock',
            'OnAfterIBlockElementSetPropertyValues',
            $calledClass . '::onAfterSetPropertyValues',
            false,
            100
        );
        $eventManager->addEventHandler(
            'iblock',
            'OnAfterIBlockElementSetPropertyValuesEx',
            $calledClass . '::onAfterSetPropertyValuesEx',
            false,
            100
        );
    }

    public static function subscribeToAll(EventManager $eventManager) {
        static::subscribeToBuildList($eventManager);
        static::subscribeToSetProperty($eventManager);
    }

    /**
     * Установка значений свойства
     *
     * @param mixed $elementId
     * @param mixed $iblockId
     * @param array $values
     * @param string $code
     * @return void
     */
    public static function onAfterSetPropertyValues($elementId, $iblockId, array $values, $code) {
        $valuesValid = $values;
        if (strlen($code) > 0) {
            $valuesValid = array($code => $values);
        }
        static::onAfterSetPropertyValuesEx($elementId, $iblockId, $valuesValid, array());
    }

    /**
     * Альтернативная установка значений свойства
     *
     * @param mixed $elementId
     * @param mixed $iblockId
     * @param array $values
     * @param array $flags
     * @return void
     */
    public static function onAfterSetPropertyValuesEx($elementId, $iblockId, array $values, array $flags) {
        $imagine = static::getImagine();
        if ($imagine !== null) {
            $app = Application::getInstance();
            $db = $app->getConnection(ConnectionPool::DEFAULT_CONNECTION_NAME);
            $docRoot = $app->getDocumentRoot();
            $imgPropInfo = static::GetUserTypeDescription();
            $imgPropType = $imgPropInfo['PROPERTY_TYPE'];
            $imgPropUt = $imgPropInfo['USER_TYPE'];
            foreach ($values as $valKey => $val) {
                $prop = \CIBlockProperty::GetByID($valKey, $iblockId, false)
                    ->Fetch();
                if ($prop !== false && $prop['PROPERTY_TYPE'] === $imgPropType && $prop['USER_TYPE'] === $imgPropUt) {
                    $propDataRs = \CIBlockElement::GetProperty(
                        $iblockId,
                        $elementId,
                        'sort',
                        'asc',
                        array('ID' => $prop['ID'])
                    );
                    $propSetts = $prop['USER_TYPE_SETTINGS'];
                    $widthMax = $propSetts['WIDTH_MAX'];
                    $heightMax = $propSetts['HEIGHT_MAX'];
                    $crop = ($propSetts['CROP'] === '1');
                    while ($propData = $propDataRs->Fetch()) {
                        $imgFileId = $propData['VALUE'];
                        $imgFile = \CFile::GetFileArray($imgFileId, false);
                        if (is_array($imgFile)) {
                            $imgFilePath = $docRoot . $imgFile['SRC'];
                            $img = $imagine->open($imgFilePath);
                            $imgChanged = static::modify($img, $widthMax, $heightMax, $crop);
                            if ($imgChanged) {
                                $img->save(null, array('jpeg_quality' => 100, 'png_compression_level' => 0));
                                $imgSize = $img->getSize();
                                $db->queryExecute(
                                    sprintf(
                                        'UPDATE b_file
                                        SET WIDTH = %1$F, HEIGHT = %2$F, FILE_SIZE = %3$d
                                        WHERE ID = %4$u',
                                        $imgSize->getWidth(),
                                        $imgSize->getHeight(),
                                        filesize($imgFilePath),
                                        $imgFileId
                                    )
                                );
                                \CFile::ResizeImageDelete($imgFile);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Описание свойства
     *
     * @return array
     */
    public static function getUserTypeDescription() {
        $calledClass = get_called_class();

        return array(
            'PROPERTY_TYPE' => 'F',
            'USER_TYPE' => 'Image',
            'DESCRIPTION' => 'Изображение',
            'CheckFields' => $calledClass . '::checkFields',
            'ConvertToDB' => $calledClass . '::convertToDB',
            'PrepareSettings' => $calledClass . '::prepareSettings',
            'GetSettingsHTML' => $calledClass . '::getSettingsHTML',
            'GetPropertyFieldHtml' => $calledClass . '::getPropertyFieldHtml',
            'GetPropertyFieldHtmlMulty' => $calledClass . '::getPropertyFieldHtmlMulty',
            'GetAdminListViewHTML' => $calledClass . '::getAdminListViewHTML'
        );
    }

    /**
     * Показ в списке
     *
     * @param array $info
     * @param array $data
     * @param array $htmlInfo
     * @return string
     */
    public static function getAdminListViewHTML(array $info, array $data, array $htmlInfo) {
        return \CFile::ShowFile(
            $data['VALUE'],
            0,
            100,
            0,
            true,
            false,
            false,
            0,
            0
        );
    }

    /**
     * Подготовка настроек
     * @param array $info
     * @return array
     */
    public static function prepareSettings(array $info) {
        $settings = $info['USER_TYPE_SETTINGS'];
        $vals = array(
            'WIDTH_MAX' => '',
            'HEIGHT_MAX' => '',
            'CROP' => '0'
        );
        if (is_array($settings)) {
            foreach ($settings as $valKey => $val) {
                $vals[$valKey] = $val;
            }
        }

        return $vals;
    }

    /**
     * Настройки для свойства
     *
     * @param array $info
     * @param array $htmlInfo
     * @param array $fields
     * @return string
     */
    public static function getSettingsHTML(array $info, array $htmlInfo, array &$fields) {
        $fields = array(
            'HIDE' => array('ROW_COUNT', 'COL_COUNT', 'DEFAULT_VALUE'),
            'USER_TYPE_SETTINGS_TITLE' => 'Настройки изображения'
        );
        $html = '';
        $fieldNamePref = $htmlInfo['NAME'];
        $settings = $info['USER_TYPE_SETTINGS'];

        $html .= sprintf(
            '<tr>
                <td>Максимальная ширина:</td>
                <td>
                    <input type="number" name="%1$s[WIDTH_MAX]" value="%2$s"/>
                </td>
            </tr>',
            $fieldNamePref,
            $settings['WIDTH_MAX']
        );

        $html .= sprintf(
            '<tr>
                <td>Максимальная высота:</td>
                <td>
                    <input type="number" name="%1$s[HEIGHT_MAX]" value="%2$s"/>
                </td>
            </tr>',
            $fieldNamePref,
            $settings['HEIGHT_MAX']
        );

        $html .= sprintf(
            '<tr>
                <td>Обрезать по пропорциям:</td>
                <td>
                    <input type="hidden" value="0" name="%1$s[CROP]"/>
                    <input type="checkbox" value="1" name="%1$s[CROP]" %2$s/>
                </td>
            </tr>',
            $fieldNamePref,
            ($settings['CROP'] === '1' ? 'checked' : '')
        );

        return $html;
    }

    /**
     * Сохранение в БД
     *
     * @param array $info
     * @param array $data
     * @return mixed
     */
    public static function convertToDB(array $info, array $data) {
        $val = $data['VALUE'];
        if (is_array($val)) {
            $imgPath = $val['tmp_name'];
            if (file_exists($imgPath)) {
                $imagine = static::getImagine();
                if ($imagine !== null) {
                    $img = $imagine->open($imgPath);
                    $settings = $info['USER_TYPE_SETTINGS'];
                    $widthMax = (float)$settings['WIDTH_MAX'];
                    $heightMax = (float)$settings['HEIGHT_MAX'];
                    $crop = ($settings['CROP'] === '1');
                    $imgChanged = static::modify($img, $widthMax, $heightMax, $crop);
                    if ($imgChanged) {
                        $img->save(null, array('jpeg_quality' => 100, 'png_compression_level' => 0));
                        $val['size'] = filesize($imgPath);
                    }
                }
                $imgFileId = \CFile::SaveFile($val, 'iblock_image', false, false);

                return $imgFileId;
            }
        }

        return $val;
    }

    /**
     * Проверка значения
     *
     * @param array $info
     * @param array $data
     * @return array
     */
    public static function checkFields(array $info, array $data) {
        return array();
    }

    /**
     * Отображение в форме редактирования
     *
     * @param array $info
     * @param array $data
     * @param array $htmlInfo
     * @return string
     */
    public static function getPropertyFieldHtml(array $info, array $data, array $htmlInfo) {
        $htmlName = 'n0';
        if (strlen($htmlInfo['VALUE']) > 0) {
            $htmlName = str_replace('[VALUE]', '', $htmlInfo['VALUE']);
        }

        return \CFileInput::Show(
            $htmlName,
            $data['VALUE'],
            array(
                'IMAGE' => 'Y',
                'PATH' => 'Y',
                'FILE_SIZE' => 'Y',
                'DIMENSIONS' => 'Y',
                'IMAGE_POPUP' => 'Y',
                'MAX_SIZE' => array('W' => 200, 'H' => 200)
            ),
            array(
                'upload' => true,
                'medialib' => false,
                'file_dialog' => false,
                'cloud' => false,
                'del' => true,
                'description' => false
            )
        );
    }

    /**
     * Отображение в форме редактирования для множественного свойства
     *
     * @param array $info
     * @param array $dataList
     * @param array $htmlInfo
     * @return string
     */
    public static function getPropertyFieldHtmlMulty(array $info, array $dataList, array $htmlInfo) {
        $htmlVals = array();
        $propPref = 'PROP[' . $info['ID'] . ']';
        foreach ($dataList as $dataKey => $data) {
            $htmlVals[$propPref . '[' . $dataKey . ']'] = $data['VALUE'];
        }
        $html = \CFileInput::ShowMultiple(
            $htmlVals,
            $propPref . '[n#IND#]',
            array(
                'IMAGE' => 'Y',
                'PATH' => 'Y',
                'FILE_SIZE' => 'Y',
                'DIMENSIONS' => 'Y',
                'IMAGE_POPUP' => 'Y',
                'MAX_SIZE' => array('W' => 200, 'H' => 200)
            ),
            false,
            array(
                'upload' => true,
                'medialib' => false,
                'file_dialog' => false,
                'cloud' => false,
                'del' => true,
                'description' => false
            )
        );

        return $html;
    }

    /**
     * Изменение размера изображения
     *
     * @param ImageInterface $img
     * @param int|float $widthMax
     * @param int|float $heightMax
     * @param boolean $crop
     * @return boolean Изменено?
     */
    private static function modify(ImageInterface $img, $widthMax, $heightMax, $crop) {
        $imgChanged = false;

        /* Обрезка изображения */
        $imgCropX = 0;
        $imgCropY = 0;
        $imgSizeOld = $img->getSize();
        $imgWidthOld = $imgSizeOld->getWidth();
        $imgWidthCrop = $imgWidthOld;
        $imgHeightOld = $imgSizeOld->getHeight();
        $imgHeightCrop = $imgHeightOld;
        if ($crop && $widthMax > 0 && $heightMax > 0) {
            $isWidthCrop = (($imgWidthOld * ($heightMax / $widthMax)) > $imgHeightOld);
            if ($isWidthCrop) {
                $imgWidthCrop = $imgHeightOld / ($heightMax / $widthMax);
                $imgCropX = ($imgWidthOld - $imgWidthCrop) / 2;
            } else {
                $imgHeightCrop = $imgWidthOld * ($heightMax / $widthMax);
                $imgCropY = ($imgHeightOld - $imgHeightCrop) / 2;
            }
            if ($imgWidthOld > $imgWidthCrop || $imgHeightOld > $imgHeightCrop) {
                $img->crop(
                    new Point($imgCropX, $imgCropY),
                    new Box($imgWidthCrop, $imgHeightCrop)
                );
                $imgChanged = true;
            }
        }

        /* Изменение размера изображения */
        $imgWidthNew = $imgWidthCrop;
        $imgHeightNew = $imgHeightCrop;
        if ($widthMax > 0 && $imgWidthNew > $widthMax) {
            $wResizeRatio = $widthMax / $imgWidthNew;
            $imgWidthNew = $widthMax;
            $imgHeightNew *= $wResizeRatio;
        }
        if ($heightMax > 0 && $imgHeightNew > $heightMax) {
            $hResizeRatio = $heightMax / $imgHeightNew;
            $imgHeightNew = $heightMax;
            $imgWidthNew *= $hResizeRatio;
        }
        if ($imgWidthNew !== $imgWidthCrop || $imgHeightNew !== $imgHeightCrop) {
            $img->resize(
                new Box($imgWidthNew, $imgHeightNew),
                ImageInterface::FILTER_LANCZOS
            );
            $imgChanged = true;
        }

        return $imgChanged;
    }

    /**
     * Получение объекта для обработки изображений
     *
     * @return ImagineInterface|null
     */
    private static function getImagine() {
        $imagine = null;
        $propCfg = Configuration::getValue('image_property');
        if (!empty($propCfg['imagine_extension'])) {
            $imagineExt = $propCfg['imagine_extension'];
            $imagineClass = 'Imagine\\' . (ucfirst(strtolower($imagineExt))) . '\\Imagine';
            if (class_exists($imagineClass, true)) {
                $imagine = new $imagineClass();
            }
        }

        return $imagine;
    }
}