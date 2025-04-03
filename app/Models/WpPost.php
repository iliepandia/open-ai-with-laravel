<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use PhpParser\Lexer\TokenEmulator\AttributeEmulator;

class WpPost extends Model
{
    protected $table = "soar_posts";

    protected $connection = "mysql-live";

    protected $primaryKey = "ID";
    public function params() : HasMany{
        return $this->hasMany(WpPostMeta::class, "post_id", "ID");
    }

    public function getParam($key): ?string
    {
        if(!$this->relationLoaded('params')){
            $this->load('params');
        }

        foreach($this->params as $param){
            if( $param['meta_key'] == $key ){
                return $param['meta_value'];
            }
        }
        return null;
    }
    public function thumbnail() : Attribute
    {
        return Attribute::make(
            get: function(mixed $value, array $attributes ){
                $thumbnailId = $this->getParam('_thumbnail_id');
                if($thumbnailId){
                    $attachment = WpPost::where('ID', $thumbnailId)->first();
                    return $attachment?->guid; // Return the URL of the thumbnail
                }
                return null;
            }
        );
    }

    public function price() : Attribute
    {
        return Attribute::make(
          get: function(mixed $value, array $attributes){
              return floatval($this->getParam('_regular_price'));
            }
        );
    }
    public function description() : Attribute
    {
        return Attribute::make(
            get: function(mixed $value, array $attributes ){
                $description = $this->post_excerpt . "\n" . $this->post_content;
                return str_replace("\r\n", "\n", $description);
            }
        );
    }

    public function buyNowLink() : Attribute
    {
        return Attribute::make(
            get: function(mixed $value, array $attributes ){
                return $this->getParam('buy_now_link');
            }
        );
    }

    public function previewFile() : Attribute
    {
        return Attribute::make(
              get: function(mixed $value, array $attributes ){
                  $content = $this->post_content;
                  $matches = [];
                  //<a class="button" href="https://ineliabenz.com/wp-content/uploads/2017/10/interview-with-an-alien-by-inelia-benz-first-chapter.pdf">Read the First Chapter</a>
                  if(preg_match( '#"button" href="(.*)">.?Read the First Chapter#iu', $content, $matches )){
                      return $matches[1];
                  }
                  return null;
                }
        );
    }

    public function previewType() : Attribute
    {
        //For now all previews are of type book
        return Attribute::make(
            get: fn(mixed $value)=>"book"
        );
    }
    public function previewLabel() : Attribute
    {
        return Attribute::make(
              get: function(mixed $value, array $attributes ){
                  $content = $this->post_content;
                  $matches = [];
                  //<a class="button" href="https://ineliabenz.com/wp-content/uploads/2017/10/interview-with-an-alien-by-inelia-benz-first-chapter.pdf">Read the First Chapter</a>
                  if(preg_match( '#"button" href="(.*)">(.*)?</a>#iu', $content, $matches )){
                      return $matches[2];
                  }
                  return null;
                }
        );
    }

    public function name() : Attribute
    {
        return Attribute::make(
            get: fn(mixed $value)=>$this->post_title
        );
    }
    public function slug() : Attribute
    {
        return Attribute::make(
            get: fn(mixed $value)=>$this->post_name
        );
    }

    public function url() : Attribute
    {
        return Attribute::make(
            get: fn(mixed $value, array $attributes )=>"https://ineliabenz.com/" . $attributes['post_name']
        );
    }

    public function scopeActivePosts( Builder $query ) : void
    {
        $query
            ->with('params')
            ->where('post_type', 'post')
            ->where('post_status', 'publish');
    }

    public function scopeActiveProducts( Builder $query ) : void
    {
        $query
            ->with('params')
            ->where('post_type', 'product')
            ->where('post_status', 'publish')
        ;
    }

}
