<?php
/**
 * Plugin Name: AIFE Profiles (Scored Questionnaire)
 * Description: Mixed radio/checkbox questionnaire that assigns users to top 3 categories and recommends posts by matching WP tags.
 * Version: 0.3.0
 */

if (!defined('ABSPATH')) exit;

class AIFE_Profile_Scoring {
 const META_TOP3_SLUGS = 'aife_top3_category_slugs';
 const META_TAG_IDS = 'aife_tag_ids';

 private static function categories() {
 return [
 'beginner' => 'beginner',
 'advanced' => 'advanced',
 'artists' => 'artists',
 'entrepreneurs' => 'entrepreneurs',
 'homemakers' => 'homemakers',
 'job_seekers' => 'job-seekers',
 'office_workers' => 'office-workers',
 'programmers' => 'programmers',
 'students' => 'students',
 ];
 }

 private static function questions() {
 return [
 [
 'key' => 'q1',
 'type' => 'radio',
 'label' => 'How would you best describe your knowledge of AI (Artificial Intelligence)?',
 'required' => true,
 'options' => [
 'q1_a' => ['label' => 'I know nothing about AI', 'scores' => ['beginner'=>2]],
 'q1_b' => ['label' => 'I know about AI, but not how it actually works', 'scores' => ['beginner'=>1]],
 'q1_c' => ['label' => 'I am knowledgeable about AI and some of its inner workings', 'scores' => ['advanced'=>1]],
 'q1_d' => ['label' => 'I am well versed in the technical aspects of AI', 'scores' => ['advanced'=>2]],
 ],
 ],
 [
 'key' => 'q2',
 'type' => 'radio',
 'label' => 'How would you best describe your experience using AI (Artificial Intelligence)?',
 'required' => true,
 'options' => [
 'q2_a' => ['label' => 'I have no experience using AI', 'scores' => ['beginner'=>2]],
 'q2_b' => ['label' => 'I use AI on occasion, but don’t actively seek it out', 'scores' => ['advanced'=>1,'beginner'=>1]],
 'q2_c' => ['label' => 'I use AI frequently in my daily life', 'scores' => ['advanced'=>2]],
 'q2_d' => ['label' => 'I have a background in AI development and research', 'scores' => ['advanced'=>3,'programmers'=>2]],
 'q2_e' => ['label' => 'I actively abstain from using AI in my daily life', 'scores' => ['artists'=>1,'beginner'=>1]],
 ],
 ],
 [
 'key' => 'q3',
 'type' => 'radio',
 'label' => 'How would you best describe your employment status?',
 'required' => true,
 'options' => [
 'q3_a' => ['label' => 'Currently employed', 'scores' => ['office_workers'=>3,'job_seekers'=>1]],
 'q3_b' => ['label' => 'Actively searching for employment', 'scores' => ['job_seekers'=>3,'office_workers'=>1]],
 'q3_c' => ['label' => 'Freelance worker or entrepreneur', 'scores' => ['entrepreneurs'=>3,'artists'=>1,'programmers'=>1]],
 'q3_d' => ['label' => 'Pursuing my education full time', 'scores' => ['students'=>3]],
 'q3_e' => ['label' => 'Stay at home parent or caretaker', 'scores' => ['homemakers'=>3]],
 'q3_f' => ['label' => 'Otherwise not employed or seeking employment', 'scores' => ['homemakers'=>2,'artists'=>1]],
 ],
 ],
 [
 'key' => 'q4',
 'type' => 'checkbox',
 'label' => 'What have you used AI for in the past?',
 'required' => false,
 'options' => [
 'q4_a' => ['label' => 'I have never used anything related to AI', 'scores' => ['beginner'=>2]],
 'q4_b' => ['label' => 'I have conversed with AI models (ex: ChatGPT, Gemini, Claude)', 'scores' => ['entrepreneurs'=>1]],
 'q4_c' => ['label' => 'I have used AI image generators (ex: DALL-E, Midjourney, Adobe Firefly)', 'scores' => ['artists'=>2]],
 'q4_d' => ['label' => 'I have used AI video generators (ex: Sora, Google Veo)', 'scores' => ['artists'=>1,'entrepreneurs'=>1]],
 'q4_e' => ['label' => 'I have programmed with the assistance of AI', 'scores' => ['programmers'=>2,'advanced'=>1]],
 'q4_f' => ['label' => 'I have consulted AI when making travel plans or other decisions', 'scores' => ['homemakers'=>2,'entrepreneurs'=>1]],
 ],
 ],
 [
 'key' => 'q5',
 'type' => 'checkbox',
 'label' => 'What areas are you interested in learning about the impact of AI on?',
 'required' => false,
 'options' => [
 'q5_a' => ['label' => 'STEM (Science, Technology, Engineering and Mathematics)', 'scores' => ['programmers'=>2]],
 'q5_b' => ['label' => 'The Arts (Visual Art, Music, Writing and Film)', 'scores' => ['artists'=>2]],
 'q5_c' => ['label' => 'Education (Teachers and Students)', 'scores' => ['students'=>1]],
 'q5_d' => ['label' => 'Business and Finance', 'scores' => ['entrepreneurs'=>1,'job_seekers'=>1,'office_workers'=>1]],
 ],
 ],
 ];
 }

 public static function init() {
 add_shortcode('aife_questionnaire', [__CLASS__, 'sc_questionnaire']);
 add_shortcode('aife_recommendations', [__CLASS__, 'sc_recommendations']);
 }

 private static function tag_id_from_slug($slug) {
 $t = get_term_by('slug', $slug, 'post_tag');
 return ($t && !is_wp_error($t)) ? (int)$t->term_id : 0;
 }

 private static function compute_top3($scores) {
 $b = (int)($scores['beginner'] ?? 0);
 $a = (int)($scores['advanced'] ?? 0);
 $level = ($a > $b) ? 'advanced' : 'beginner'; // tie => beginner

 $interest_slugs = ['artists','entrepreneurs','homemakers','job_seekers','office_workers','programmers','students'];
 $interest_scores = [];
 foreach ($interest_slugs as $s) $interest_scores[$s] = (int)($scores[$s] ?? 0);

 uasort($interest_scores, function($x, $y){
 if ($x === $y) return 0;
 return ($x > $y) ? -1 : 1;
 });

 $top2 = array_slice(array_keys($interest_scores), 0, 2);
 return array_values(array_unique(array_merge([$level], $top2)));
 }

 private static function clear_user_results($user_id) {
 delete_user_meta($user_id, self::META_TOP3_SLUGS);
 delete_user_meta($user_id, self::META_TAG_IDS);
 }

 public static function sc_questionnaire() {
 if (!is_user_logged_in()) return '<p>You need to be logged in to use this.</p>';

 $user_id = get_current_user_id();
 $saved_top3 = get_user_meta($user_id, self::META_TOP3_SLUGS, true);
 if (!is_array($saved_top3)) $saved_top3 = [];

 $out = '';
 $errors = [];

 // Retake (clear saved results)
 if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aife_retake']) && isset($_POST['aife_retake_nonce'])) {
 if (!wp_verify_nonce($_POST['aife_retake_nonce'], 'aife_retake')) {
 return '<p>Security check failed. Please reload and try again.</p>';
 }
 self::clear_user_results($user_id);
 $saved_top3 = [];
 $out .= '<p><strong>Cleared.</strong> You can retake the questionnaire now.</p>';
 }

 // Save
 if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aife_save']) && isset($_POST['aife_nonce'])) {
 if (!wp_verify_nonce($_POST['aife_nonce'], 'aife_save')) {
 return '<p>Security check failed. Please reload and try again.</p>';
 }

 $scores = [];
 foreach (array_keys(self::categories()) as $cat) $scores[$cat] = 0;

 foreach (self::questions() as $q) {
 $key = $q['key'];

 if ($q['type'] === 'radio') {
 $val = sanitize_text_field($_POST[$key] ?? '');
 if (!empty($q['required']) && $val === '') {
 $errors[] = 'Please answer: ' . $q['label'];
 continue;
 }
 if ($val && isset($q['options'][$val])) {
 foreach (($q['options'][$val]['scores'] ?? []) as $cat => $pts) {
 if (isset($scores[$cat])) $scores[$cat] += (int)$pts;
 }
 }
 } else { // checkbox
 $vals = $_POST[$key] ?? [];
 if (is_array($vals)) {
 foreach ($vals as $val) {
 $val = sanitize_text_field($val);
 if (!$val || !isset($q['options'][$val])) continue;
 foreach (($q['options'][$val]['scores'] ?? []) as $cat => $pts) {
 if (isset($scores[$cat])) $scores[$cat] += (int)$pts;
 }
 }
 }
 }
 }

 if (empty($errors)) {
 $top3 = self::compute_top3($scores);

 $cat_to_tag = self::categories();
 $tag_ids = [];
 foreach ($top3 as $cat_slug) {
 $tag_slug = $cat_to_tag[$cat_slug] ?? '';
 if ($tag_slug) {
 $tid = self::tag_id_from_slug($tag_slug);
 if ($tid) $tag_ids[] = $tid;
 }
 }

 update_user_meta($user_id, self::META_TOP3_SLUGS, $top3);
 update_user_meta($user_id, self::META_TAG_IDS, $tag_ids);

 $saved_top3 = $top3;
 $out .= '<p><strong>Saved!</strong> Your categories: ' . esc_html(implode(', ', array_map('ucwords', str_replace('_',' ', $top3)))) . '</p>';
 }
 }

 if (!empty($errors)) {
 $out .= '<div style="border:1px solid #d63638;padding:10px;margin:10px 0">';
 $out .= '<strong>Please fix the following:</strong><ul>';
 foreach ($errors as $e) $out .= '<li>' . esc_html($e) . '</li>';
 $out .= '</ul></div>';
 }

 // Render form
 $out .= '<form method="post">';
 $out .= wp_nonce_field('aife_save', 'aife_nonce', true, false);
 $out .= '<input type="hidden" name="aife_save" value="1">';

 foreach (self::questions() as $q) {
 $out .= '<fieldset style="margin:16px 0;padding:12px;border:1px solid #ddd">';
 $out .= '<legend><strong>' . esc_html($q['label']) . '</strong> ';
 $out .= ($q['type']==='radio') ? '<em>(Select one)</em>' : '<em>(Select all that apply)</em>';
 if (!empty($q['required']) && $q['type']==='radio') $out .= ' <span style="color:#d63638">*</span>';
 $out .= '</legend>';

 foreach ($q['options'] as $value => $opt) {
 $type = ($q['type'] === 'radio') ? 'radio' : 'checkbox';
 $name = ($type === 'radio') ? $q['key'] : $q['key'].'[]';

 $out .= '<label style="display:block;margin:6px 0">';
 $out .= '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"> ';
 $out .= esc_html($opt['label']);
 $out .= '</label>';
 }

 $out .= '</fieldset>';
 }

 $out .= '<button type="submit">Save my results</button>';
 $out .= '</form>';

 // Retake button (clears saved results)
 $out .= '<form method="post" style="margin-top:10px">';
 $out .= wp_nonce_field('aife_retake', 'aife_retake_nonce', true, false);
 $out .= '<button type="submit" name="aife_retake" value="1" onclick="return confirm(\'Clear your saved results and retake?\')">Retake questionnaire</button>';
 $out .= '</form>';

 if (!empty($saved_top3)) {
 $out .= '<p><small>Current saved categories: ' . esc_html(implode(', ', array_map('ucwords', str_replace('_',' ', $saved_top3)))) . '</small></p>';
 }

 return $out;
 }

 public static function sc_recommendations($atts) {
 if (!is_user_logged_in()) return '<p>You need to be logged in to use this.</p>';

 $atts = shortcode_atts(['limit' => 6], $atts);

 $tag_ids = get_user_meta(get_current_user_id(), self::META_TAG_IDS, true);
 if (!is_array($tag_ids) || empty($tag_ids)) {
 return '<p>No saved categories yet—complete the questionnaire first.</p>';
 }

 $q = new WP_Query([
 'post_type' => 'post',
 'post_status' => 'publish',
 'posts_per_page' => (int)$atts['limit'],
 'tag__in' => array_map('intval', $tag_ids),
 'ignore_sticky_posts' => true,
 ]);

 if (!$q->have_posts()) return '<p>No recommendations found yet.</p>';

 ob_start();
 echo '<ul>';
 while ($q->have_posts()) { $q->the_post();
 echo '<li><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></li>';
 }
 echo '</ul>';
 wp_reset_postdata();
 return ob_get_clean();
 }
}

AIFE_Profile_Scoring::init();
