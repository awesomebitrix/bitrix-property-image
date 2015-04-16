<?php
namespace Devel59\Bitrix\Iblock\Property;

use Bitrix\Main\Config\Configuration;
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
     * Добавление обработчика к событию
     *
     * @param EventManager $eventManager
     * @return void
     */
    public static function subscribeToEvents(EventManager $eventManager) {
        $calledClass = get_called_class();
        $eventManager->addEventHandler(
            'iblock',
            'OnIBlockPropertyBuildList',
            $calledClass . '::GetUserTypeDescription',
            false,
            100
        );
    }

    /**
     * Описание свойства
     *
     * @return array
     */
    public static function GetUserTypeDescription() {
        $calledClass = get_called_class();

        return array(
            'PROPERTY_TYPE' => 'F',
            'USER_TYPE' => 'Image',
            'DESCRIPTION' => 'Изображение',
            'CheckFields' => $calledClass . '::CheckFields',
            'ConvertToDB' => $calledClass . '::ConvertToDB',
            'PrepareSettings' => $calledClass . '::PrepareSettings',
            'GetSettingsHTML' => $calledClass . '::GetSettingsHTML',
            'GetPropertyFieldHtml' => $calledClass . '::GetPropertyFieldHtml',
            'GetPropertyFieldHtmlMulty' => $calledClass . '::GetPropertyFieldHtmlMulty',
            'GetAdminListViewHTML' => $calledClass . '::GetAdminListViewHTML'
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
    public static function GetAdminListViewHTML(array $info, array $data, array $htmlInfo) {
        return \CFile::ShowFile(
            $data['VALUE'],
            0,
            100,
            100,
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
    public static function PrepareSettings(array $info) {
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
    public static function GetSettingsHTML(array $info, array $htmlInfo, array &$fields) {
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
    public static function ConvertToDB(array $info, array $data) {
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
                    $imgChanged = static::resize($img, $widthMax, $heightMax, $crop);
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
    public static function CheckFields(array $info, array $data) {
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
    public static function GetPropertyFieldHtml(array $info, array $data, array $htmlInfo) {
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
    public static function GetPropertyFieldHtmlMulty(array $info, array $dataList, array $htmlInfo) {
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
    private static function resize(ImageInterface $img, $widthMax, $heightMax, $crop) {
        $imgChanged = false;

        $imgCropX = 0;
        $imgCropY = 0;
        $imgSizeOld = $img->getSize();
        $imgWidthOld = $imgSizeOld->getWidth();
        $imgWidthCrop = $imgWidthOld;
        $imgHeightOld = $imgSizeOld->getHeight();
        $imgHeightCrop = $imgHeightOld;
        $isWidthCrop = (($imgWidthOld * ($heightMax / $widthMax)) > $imgHeightOld);
        if ($isWidthCrop) {
            $imgWidthCrop = $imgHeightOld / ($heightMax / $widthMax);
            $imgCropX = ($imgWidthOld - $imgWidthCrop) / 2;
        } else {
            $imgHeightCrop = $imgWidthOld * ($heightMax / $widthMax);
            $imgCropY = ($imgHeightOld - $imgHeightCrop) / 2;
        }
        if ($crop && ($imgWidthOld > $imgWidthCrop || $imgHeightOld > $imgHeightCrop)) {
            $img->crop(
                new Point($imgCropX, $imgCropY),
                new Box($imgWidthCrop, $imgHeightCrop)
            );
            $imgChanged = true;
        }

        $imgWidthNew = $imgWidthCrop;
        $imgHeightNew = $imgHeightCrop;
        if ($imgWidthNew > $widthMax) {
            $wResizeRatio = $widthMax / $imgWidthNew;
            $imgWidthNew = $widthMax;
            $imgHeightNew *= $wResizeRatio;
        }
        if ($imgHeightNew > $heightMax) {
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