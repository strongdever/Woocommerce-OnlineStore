<?php

/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */

get_header('stag', array('page' => 'top'));
?>

<section id="subpage_fv">
  <div class="part1">
    <img class="pc" src="<?php echo get_template_directory_uri() ?>/assets/images/food_fv.jpg" alt="Gallery">
    <img class="sp" src="<?php echo get_template_directory_uri() ?>/assets/images/food_fv_sp.jpg" alt="Gallery">
    <h2>
      Food
    </h2>
  </div>
</section>
<section id="gallery_sec1">
  <div class="part1 fixedcontainer">
    <h2>
      地の恵みは、大人旅の醍醐味
    </h2>
    <p>
いつからだろうか？<br>
住んでいる場所を離れて、出向いた先では、舌鼓を打つことが喜びの一つになっている。<br>
多くの美食を知った上で、地の物を味わえることが、一番の贅沢だと知る。<br>
グランピングという、これまでの旅行ともまた異なる体験の中で、<br>
改めて味わう『食』のひとときも、想い出の風景に刻んで欲しい。
    </p>
  </div>
</section>
<div id="activity_sec2">
  <div class="part1 fixedcontainer flex_part">
    <?php $parts = get_field('parts'); 
      foreach($parts as $part):
    ?>
    <div class="block fixedcontainer flex_img_left">

      <h4 class="sp"><?php echo $part['title'] ?></h4>
      <h3 class="sp"><?php echo $part['eigo'] ?></h3>
      <div class="flex_img">
        <div class="slicker_parts1">
          <?php foreach($part['gallery'] as $image):?>
          <div class="slicker_part">
            <img src="<?php echo $image?>" alt="">
          </div>
          <?php endforeach ?>
        </div>
        <div class="slicker_parts2">
          <?php foreach($part['gallery'] as $image):?>
          <div class="slicker_part">
            <img src="<?php echo $image?>" alt="">
          </div>
          <?php endforeach ?>
        </div>
      </div>
      <div class="flex_body">
        <h3 class="pc"><?php echo $part['eigo'] ?></h3>
        <h4 class="pc"><?php echo $part['title'] ?></h4>
        <p><?php echo $part['content'] ?></p>
      </div>
    </div>
    <?php endforeach;?>
  </div>
</div>
<div id="gallery_sec3">
  <div class="part1 fixedcontainer">
  </div>
</div>
<?php
get_footer('stag', array('page' => 'top'));
