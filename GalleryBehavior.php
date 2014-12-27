<?php

namespace zxbodya\yii2\galleryManager;

use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use zxbodya\yii2\galleryManager\models\Gallery;
use zxbodya\yii2\galleryManager\models\GalleryPhoto;

/**
 * Behavior for adding gallery to any model.
 *
 * @author Bogdan Savluk <savluk.bogdan@gmail.com>
 */
class GalleryBehavior extends Behavior
{
    /** @var string Model attribute name to store created gallery id */
    public $idAttribute;
    /**
     * @var array Settings for image auto-generation
     * @example
     *  array(
     *       'small' => array(
     *              'resize' => array(200, null),
     *       ),
     *      'medium' => array(
     *              'resize' => array(800, null),
     *      )
     *  );
     */
    public $versions;
    /** @var boolean does images in gallery need names */
    public $name = true;
    /** @var boolean does images in gallery need descriptions */
    public $description = true;

    /** @var string Extensions for gallery images */
    public $extension = 'jpg';

    private $_gallery;
    
    
//    public function attach($owner)
//    {
//        parent::attach($owner);
//        
//    }
    
    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
    }

    /** Will create new gallery after save if no associated gallery exists */
    public function beforeSave($event)
    {
        
        if ($event->isValid) {
            if (empty($this->owner->{$this->idAttribute})) {
                $gallery = new Gallery();
                $gallery->name = $this->name;
                $gallery->description = $this->description;
                $gallery->setVersions($this->versions);
                $gallery->extension = $this->extension;
                $gallery->save(false);
                $this->owner->{$this->idAttribute} = $gallery->id;
            } else {
                Yii::info(
                    'Gallery configuration change in web-worker process, this should be in migrations'
                );
                $this->changeConfig();
            }
        }
    }

    /** Will remove associated Gallery before object removal */
    public function beforeDelete($event)
    {
        $gallery = $this->getGallery();
        if ($gallery !== null) {
            $gallery->delete();
        }
        parent::beforeDelete($event);
    }

    /** Method for changing gallery configuration and regeneration of images versions */
    public function changeConfig($force = false)
    {
        $gallery = $this->getGallery();
        if ($gallery == null) {
            return;
        }


        $gallery->name = $this->name;
        $gallery->description = $this->description;


        if ($gallery->versions_data != serialize(
                $this->versions
            ) || $force || $gallery->extension != $this->extension
        ) {
            foreach ($gallery->galleryPhotos as $photo) {
                $photo->removeImages();
            }
            if ($gallery->extension != $this->extension) {
                foreach ($gallery->galleryPhotos as $photo) {
                    $photo->changeExtension($gallery->extension, $this->extension);
                }
                $gallery->extension = $this->extension;
            }
            $gallery->versions = $this->versions;
            $gallery->save();

            $gallery = Gallery::find()->where(['id'=>$gallery->id])->one();
            foreach ($gallery->galleryPhotos as $photo) {
                $photo->updateImages();
            }
        }
        $gallery->save();
    }

    /** @return Gallery Returns gallery associated with model */
    public function getGallery()
    {
        if (empty($this->_gallery)) {
            $this->_gallery = Gallery::find()->where(['id'=>$this->owner->{$this->idAttribute}])->one();
        }

        return $this->_gallery;
    }

    /** @return GalleryPhoto[] Photos from associated gallery */
    public function getPhotos()
    {
        return GalleryPhoto::find()->andWhere(['gallery_id'=>$this->owner->{$this->idAttribute}])->orderBy("`rank` asc")->all();
    }

    /** @return GalleryPhoto[] Photos from associated gallery */
    public function getFirstPhoto()
    {
        return GalleryPhoto::find()->andWhere(['gallery_id'=>$this->owner->{$this->idAttribute}])->orderBy("`rank` asc")->one();
    }

    /** @return GalleryPhoto[] Photos from associated gallery */
    public function getPhotoCount()
    {
        return GalleryPhoto::find()->andWhere(['gallery_id'=>$this->owner->{$this->idAttribute}])->count();
    }
}
