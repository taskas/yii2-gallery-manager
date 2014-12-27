<?php

namespace zxbodya\yii2\galleryManager\models;

use Yii;
use yii\imagine\Image;

/**
 * This is the model class for table "{{%gallery_photo}}".
 *
 * @property integer $id
 * @property integer $gallery_id
 * @property integer $rank
 * @property string  $name
 * @property string  $description
 * @property string  $file_name
 *
 * @property Gallery $gallery
 *
 * @author Bogdan Savluk <savluk.bogdan@gmail.com>
 */
class GalleryPhoto extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%gallery_photo}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['gallery_id'], 'required'],
            [['gallery_id', 'rank'], 'integer'],
            [['description'], 'string'],
            [['name', 'file_name'], 'string', 'max' => 255]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'gallery_id' => 'Gallery ID',
            'rank' => 'Rank',
            'name' => 'Name',
            'description' => 'Description',
            'file_name' => 'File Name',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getGallery()
    {
        return $this->hasOne(Gallery::className(), ['id' => 'gallery_id']);
    }


    public function save($runValidation = true, $attributeNames = null)
    {
        $wasNew = $this->isNewRecord;
        $res = parent::save($runValidation, $attributeNames);
        if ($this->rank === null && $wasNew) {
            $this->rank = $this->id;
            $this->setIsNewRecord(false);
            $res = $this->save();
        }

        return $res;
    }

    public function getPreview()
    {
        return Yii::getAlias('@galleryManagerRootUrl') . '/' . $this->gallery->directory . '/_' . $this->getFileName(
            ''
        ) . '.' . $this->gallery->extension;
    }

    private function getFileName($version = '')
    {
        return $this->id . $version;
    }

    public function getUrl($version = '')
    {
        return Yii::getAlias('@galleryManagerRootUrl') . '/' . $this->gallery->directory . '/' . $this->getFileName(
            $version
        ) . '.' . $this->gallery->extension;
    }


    public function changeExtension($old, $new)
    {
        //convert original
        Yii::$app->image->load(
            Yii::getAlias('@galleryManagerRoot') . '/' . $this->gallery->directory . '/' . $this->getFileName('') . '.' . $old
        )->save(
            Yii::getAlias('@galleryManagerRoot') . '/' . $this->gallery->directory . '/' . $this->getFileName('') . '.' . $new
        );

        //create image preview for gallery manager
        Yii::$app->image->load(
            Yii::getAlias('@galleryManagerRoot') . '/' . $this->gallery->directory . '/' . $this->getFileName('') . '.' . $old
        )
            ->resize(300, null)
            ->save(
                Yii::getAlias('@galleryManagerRoot') . '/' . $this->gallery->directory . '/_' . $this->getFileName(
                    ''
                ) . '.' . $new
            );

        $this->removeFile(
            Yii::getAlias('@galleryManagerRoot') . '/' . $this->gallery->directory . '/' . $this->getFileName('') . '.' . $old
        );
        $this->removeFile(
            Yii::getAlias('@galleryManagerRoot') . '/' . $this->gallery->directory . '/_' . $this->getFileName('') . '.' . $old
        );

    }

    public function setImage($path)
    {
        $originalImage = Image::getImagine()->open($path);
        //save image in original size

        $originalImage->save(
            Yii::getAlias('@galleryManagerRoot') . '/' . $this->gallery->directory . '/' . $this->getFileName(
                ''
            ) . '.' . $this->gallery->extension
        );

        //create image preview for gallery manager
        $originalImage
            ->copy()
            ->resize($originalImage->getSize()->widen(300))->save(
                Yii::getAlias('@galleryManagerRoot') . '/' . $this->gallery->directory . '/_' . $this->getFileName(
                    ''
                ) . '.' . $this->gallery->extension
            );

        $this->updateImages($originalImage);
    }

    public function delete()
    {
        $this->removeFile(
            Yii::getAlias('@galleryManagerRoot') . '/' . $this->gallery->directory . '/' . $this->getFileName(
                ''
            ) . '.' . $this->gallery->extension
        );
        $this->removeFile(
            Yii::getAlias('@galleryManagerRoot') . '/' . $this->gallery->directory . '/_' . $this->getFileName(
                ''
            ) . '.' . $this->gallery->extension
        );

        $this->removeImages();

        return parent::delete();
    }

    private function removeFile($fileName)
    {
        if (file_exists($fileName)) {
            @unlink($fileName);
        }
    }

    public function removeImages()
    {
        foreach ($this->gallery->versions as $version => $actions) {
            $this->removeFile(
                Yii::getAlias('@galleryManagerRoot') . '/' . $this->gallery->directory . '/' . $this->getFileName(
                    $version
                ) . '.' . $this->gallery->extension
            );
        }
    }

    /**
     * Regenerate image versions
     */
    public function updateImages($originalImage = null)
    {
        if ($originalImage === null) {
            $originalImage = Image::getImagine()->open(
                Yii::getAlias('@galleryManagerRoot') . '/' . $this->gallery->directory . '/' . $this->getFileName(
                    ''
                ) . '.' . $this->gallery->extension
            );
        }
        foreach ($this->gallery->versions as $version => $actions) {
            $this->removeFile(
                Yii::getAlias('@galleryManagerRoot') . '/' . $this->gallery->directory
                . '/' . $this->getFileName($version) . '.' . $this->gallery->extension
            );


            foreach($actions as $method => $args){
                if($method=='thumbnail'){
                    $originalImage
                    ->copy()
                    ->$method($args[0])->save(
                        Yii::getAlias('@galleryManagerRoot') . '/' . $this->gallery->directory . '/' . $this->getFileName(
                        $version
                    ) . '.' . $this->gallery->extension
                    );
                }elseif($method=='widen'){
                    $originalImage
                    ->copy()
                    ->resize($originalImage->getSize()->widen(800))->save(
                        Yii::getAlias('@galleryManagerRoot') . '/' . $this->gallery->directory . '/' . $this->getFileName(
                        $version
                    ) . '.' . $this->gallery->extension
                    );
                }
            }

//            $originalImage->save(
//                Yii::getAlias('@galleryManagerRoot') . '/' . $this->gallery->directory . '/' . $this->getFileName(
//                    $version
//                ) . '.' . $this->gallery->extension
//            );
        }
    }

    private $_sizes = array();

    private function getSize($version = '')
    {
        if (!isset($this->_sizes[$version])) {
            $path = Yii::getAlias('@galleryManagerRoot') . '/' . $this->gallery->directory . '/' . $this->getFileName(
                    $version
                ) . '.' . $this->gallery->extension;
            $this->_sizes[$version] = getimagesize($path);
        }

        return $this->_sizes[$version];
    }

    public function getWidth($version = '')
    {
        $s = $this->getSize($version);

        return $s[0];
    }

    public function getHeight($version = '')
    {
        $s = $this->getSize($version);

        return $s[1];
    }
}
