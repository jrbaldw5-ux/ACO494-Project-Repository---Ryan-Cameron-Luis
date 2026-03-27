<?php
/**
 * Plugin Name: AIFE Profiles (Scored Questionnaire)
 * Description: Mixed radio/checkbox questionnaire that assigns users to top 3 categories and recommends posts by matching WP tags.
 * Version: 0.4.0
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
 add_shortcode('aife_library', [__CLASS__, 'sc_library']);	 
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
        $out = '';
        $errors = [];

        // 1. Handle Retake (POST-based with Nonce)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aife_retake'])) {
            if (!isset($_POST['aife_retake_nonce']) || !wp_verify_nonce($_POST['aife_retake_nonce'], 'aife_retake')) {
                return '<p>Security check failed. Please reload.</p>';
            }
            self::clear_user_results($user_id);
            $out .= '<p style="color:green;"><strong>Profile cleared.</strong> Starting fresh...</p>';
        }

        // 2. Handle Save Logic (Your existing loop with array support)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aife_save']) && isset($_POST['aife_nonce'])) {
            if (!wp_verify_nonce($_POST['aife_nonce'], 'aife_save')) {
                return '<p>Security check failed.</p>';
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
                $out .= '<div class="notice notice-success"><p><strong>Saved!</strong> Your AI Profile is ready.</p></div>';
            }
        }

        // 3. Display Logic
        $saved_top3 = get_user_meta($user_id, self::META_TOP3_SLUGS, true);

        // If Profile Exists: Show Results + Retake Button
        if (!empty($saved_top3) && empty($errors)) {
            $out .= '<div class="aife-results-card" style="border:1px solid #ddd; padding:20px; border-radius:8px; background:#f9f9f9;">';
            $out .= '<h3>Your AI Profile</h3><ul>';
            foreach ($saved_top3 as $slug) {
                $out .= '<li>' . esc_html(ucwords(str_replace(['_', '-'], ' ', $slug))) . '</li>';
            }
            $out .= '</ul>';
            $out .= '<form method="post">' . wp_nonce_field('aife_retake', 'aife_retake_nonce', true, false);
            $out .= '<button type="submit" name="aife_retake" class="button" onclick="return confirm(\'Retake the questionnaire?\')">Retake Questionnaire</button></form>';
            $out .= '</div>';
            return $out;
        }

        // If No Profile: Show Multi-Step Form
        if (!empty($errors)) {
            $out .= '<div style="color:red; border:1px solid red; padding:10px; margin-bottom:15px;">';
            foreach ($errors as $e) $out .= '<div>' . esc_html($e) . '</div>';
            $out .= '</div>';
        }

        return $out . self::render_multi_step_ui();
    }

    private static function render_multi_step_ui() {
        $questions = self::questions();
        $total = count($questions);
        ob_start(); ?>

        <div id="aife-stepper-container" style="max-width:600px; margin:20px auto; font-family:sans-serif;">
            <!-- Progress Bar -->
            <div style="background:#eee; height:8px; border-radius:4px; margin-bottom:10px; overflow:hidden;">
                <div id="aife-progress-bar" style="width:<?php echo (100/($total+1)); ?>%; background:#0073aa; height:100%; transition:0.4s;"></div>
            </div>
            <p id="aife-step-indicator" style="font-size:0.9em; color:#666;">Question 1 of <?php echo $total; ?></p>

            <form method="post" id="aife-form">
                <?php wp_nonce_field('aife_save', 'aife_nonce'); ?>
                <input type="hidden" name="aife_save" value="1">

                <?php foreach ($questions as $index => $q): ?>
                    <div class="aife-step" id="step-<?php echo $index; ?>" style="display:<?php echo $index === 0 ? 'block' : 'none'; ?>;">
                        <fieldset style="border:1px solid #ddd; padding:20px; border-radius:8px;">
                            <legend style="font-weight:bold; padding:0 10px;"><?php echo esc_html($q['label']); ?></legend>
                            <p style="font-size:0.85em; color:#888; margin-bottom:15px;">
                                <?php echo ($q['type'] === 'radio') ? '(Select one)' : '(Select all that apply)'; ?>
                            </p>

                            <?php foreach ($q['options'] as $val => $opt): ?>
                                <?php 
                                    $name = ($q['type'] === 'radio') ? $q['key'] : $q['key'].'[]'; 
                                    $score_json = esc_attr(json_encode($opt['scores']));
                                ?>
                                <label style="display:block; margin-bottom:12px; cursor:pointer;">
                                    <input type="<?php echo $q['type']; ?>" 
                                           name="<?php echo $name; ?>" 
                                           value="<?php echo $val; ?>" 
                                           data-scores='<?php echo $score_json; ?>'
                                           <?php echo ($q['required'] && $q['type'] === 'radio') ? 'required' : ''; ?>>
                                    <?php echo esc_html($opt['label']); ?>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>

                        <div style="margin-top:20px; display:flex; justify-content:space-between;">
                            <?php if ($index > 0): ?>
                                <button type="button" class="aife-prev button">Previous</button>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>

                            <?php if ($index < $total - 1): ?>
                                <button type="button" class="aife-next button-primary">Next Question</button>
                            <?php else: ?>
                                <button type="button" id="aife-review-btn" class="button-primary">Review Results</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Summary Screen -->
                <div id="aife-summary-step" style="display:none; border:2px solid #0073aa; padding:20px; border-radius:8px;">
                    <h3>Confirm Your Profile</h3>
                    <div id="aife-live-summary" style="background:#f0f6fb; padding:15px; margin-bottom:20px; border-radius:4px;"></div>
                    <div style="display:flex; justify-content:space-between;">
                        <button type="button" id="aife-back-btn" class="button">Back</button>
                        <button type="submit" class="button-primary" style="background:#2271b1; color:#fff; padding:8px 20px; border:none; border-radius:4px; cursor:pointer;">Confirm & Save</button>
                    </div>
                </div>
            </form>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const steps = document.querySelectorAll('.aife-step');
            const bar = document.getElementById('aife-progress-bar');
            const indicator = document.getElementById('aife-step-indicator');
            const summaryStep = document.getElementById('aife-summary-step');
            const summaryContent = document.getElementById('aife-live-summary');

            function updateUI(currentIdx, isSummary = false) {
                const total = steps.length + 1;
                const progress = isSummary ? total : currentIdx + 1;
                bar.style.width = (progress / total * 100) + '%';
                indicator.innerText = isSummary ? "Step: Final Review" : "Question " + (currentIdx + 1) + " of " + steps.length;
            }

            document.getElementById('aife-form').addEventListener('click', function(e) {
                if (e.target.classList.contains('aife-next')) {
                    const current = e.target.closest('.aife-step');
                    const idx = parseInt(current.id.replace('step-', ''));
                    // Basic validation for radio
                    if (current.querySelector('input[required]') && !current.querySelector('input:checked')) {
                        alert('Please select an option to continue.'); return;
                    }
                    current.style.display = 'none';
                    document.getElementById('step-' + (idx + 1)).style.display = 'block';
                    updateUI(idx + 1);
                }

                if (e.target.classList.contains('aife-prev')) {
                    const current = e.target.closest('.aife-step');
                    const idx = parseInt(current.id.replace('step-', ''));
                    current.style.display = 'none';
                    document.getElementById('step-' + (idx - 1)).style.display = 'block';
                    updateUI(idx - 1);
                }

                if (e.target.id === 'aife-review-btn') {
                    // Calculate Live Summary
                    const totals = {};
                    document.querySelectorAll('#aife-form input:checked').forEach(input => {
                        const scores = JSON.parse(input.dataset.scores);
                        for (let cat in scores) totals[cat] = (totals[cat] || 0) + scores[cat];
                    });

                    // Mirror PHP Level Logic
                    const level = (totals['advanced'] || 0) > (totals['beginner'] || 0) ? 'Advanced' : 'Beginner';
                    
                    // Mirror PHP Interest Logic
                    const interestSlugs = ['artists','entrepreneurs','homemakers','job_seekers','office_workers','programmers','students'];
                    const topInterests = interestSlugs
                        .map(s => ({ name: s.replace('_', ' '), score: totals[s] || 0 }))
                        .sort((a, b) => b.score - a.score)
                        .slice(0, 2)
                        .map(i => i.name.charAt(0).toUpperCase() + i.name.slice(1));

                    summaryContent.innerHTML = `<strong>Your Level:</strong> ${level}<br><strong>Your Interests:</strong> ${topInterests.join(', ')}`;
                    
                    steps[steps.length - 1].style.display = 'none';
                    summaryStep.style.display = 'block';
                    updateUI(0, true);
                }

                if (e.target.id === 'aife-back-btn') {
                    summaryStep.style.display = 'none';
                    steps[steps.length - 1].style.display = 'block';
                    updateUI(steps.length - 1);
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

public static function sc_recommendations($atts) {
    if (!is_user_logged_in()) return '<p style="color:#fff; text-align:center;">You need to be logged in to view recommendations.</p>';

    $atts = shortcode_atts(['limit' => 10], $atts);

    $tag_ids = get_user_meta(get_current_user_id(), self::META_TAG_IDS, true);
    if (!is_array($tag_ids) || empty($tag_ids)) {
        return '<p style="color:#fff; text-align:center;">No saved categories yet—complete the questionnaire first.</p>';
    }

    $q = new WP_Query([
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => (int)$atts['limit'],
        'tag__in'             => array_map('intval', $tag_ids),
        'ignore_sticky_posts' => true,
    ]);

    if (!$q->have_posts()) return '<p style="color:#fff; text-align:center;">No recommendations found yet.</p>';

    ob_start();
    ?>
    <style>
        .aife-recommendations-wrapper {
            background-color: #1a1c23;
            padding: 50px 0;
            margin: 40px 0;
            border-radius: 20px;
            width: 100%;
            overflow: hidden;
        }

        .aife-scroll-container {
            display: flex;
            /* Centers cards if they don't fill the space */
            justify-content: center; 
            overflow-x: auto;
            gap: 30px;
            padding: 20px 40px 40px 40px;
            scroll-snap-type: x mandatory;
            scrollbar-width: thin;
            scrollbar-color: #4f46e5 #252833;
            box-sizing: border-box;
            width: 100%;
        }

        /* If content overflows (on smaller screens), align to start to allow scrolling */
        @media (max-width: 1200px) {
            .aife-scroll-container {
                justify-content: flex-start;
            }
        }

        /* Custom scrollbar for Chrome/Safari */
        .aife-scroll-container::-webkit-scrollbar {
            height: 8px;
        }
        .aife-scroll-container::-webkit-scrollbar-track {
            background: #1a1c23;
        }
        .aife-scroll-container::-webkit-scrollbar-thumb {
            background-color: #4f46e5;
            border-radius: 10px;
        }

        .aife-post-card {
            flex: 0 0 350px;
            scroll-snap-align: start;
            background: #252833;
            border-radius: 16px;
            /* Use box-shadow for the blue ring so it doesn't get covered by the image */
            box-shadow: 0 10px 25px rgba(0,0,0,0.3), 0 0 0 0px transparent;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            overflow: hidden; /* Clips the image to the card's rounded corners */
            text-decoration: none;
            border: 2px solid transparent; /* Placeholder for hover border */
        }

        .aife-post-card:hover {
            transform: translateY(-8px);
            border-color: #4f46e5;
            box-shadow: 0 15px 35px rgba(0,0,0,0.4), 0 0 15px rgba(79, 70, 229, 0.4);
        }

        .aife-post-image {
            height: 200px;
            width: 100%;
            overflow: hidden;
            /* Extra safety for top corners */
            border-top-left-radius: 14px;
            border-top-right-radius: 14px;
        }

        .aife-post-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.5s ease;
        }

        .aife-post-card:hover .aife-post-image img {
            transform: scale(1.05); /* Subtle zoom effect on hover */
        }
    </style>

    <div class="aife-recommendations-wrapper">
        <h2 style="text-align:center; margin-bottom:30px; color:#ffffff; font-size:32px; font-family: inherit;">Recommended for You</h2>
        
        <div class="aife-scroll-container">
            <?php while ($q->have_posts()) : $q->the_post(); ?>
                <article class="aife-post-card">
                    <div class="aife-post-image">
                        <a href="<?php the_permalink(); ?>">
                            <?php if (has_post_thumbnail()) : ?>
                                <?php the_post_thumbnail('large'); ?>
                            <?php else : ?>
                                <div style="display:flex;align-items:center;justify-content:center;height:100%;background:#1a1c23;color:#444;">No Image</div>
                            <?php endif; ?>
                        </a>
                    </div>

                    <div class="aife-post-content" style="padding:25px; display:flex; flex-direction:column; flex-grow:1;">
                        <div style="margin-bottom:12px;">
                            <?php $tags = get_the_tags(); if ($tags) : ?>
                                <span style="background:#4f46e5; color:#ffffff; font-size:11px; font-weight:700; padding:4px 10px; border-radius:6px; text-transform:uppercase; letter-spacing:0.5px;">
                                    <?php echo esc_html($tags[0]->name); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <h3 style="margin:0 0 15px 0; font-size:20px; line-height:1.3;">
                            <a href="<?php the_permalink(); ?>" style="text-decoration:none; color:#ffffff;"><?php the_title(); ?></a>
                        </h3>

                        <div style="font-size:14px; color:#abb2bf; line-height:1.6; margin-bottom:25px; flex-grow:1;">
                            <?php echo wp_trim_words(get_the_excerpt(), 18, '...'); ?>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #333745; padding-top:18px; font-size:12px; color:#7a828e;">
                            <span>By <strong style="color:#fff;"><?php the_author(); ?></strong></span>
                            <span>
                                <?php 
                                if (get_the_modified_time('U') > get_the_time('U')) {
                                    echo 'Updated ' . get_the_modified_date();
                                } else {
                                    echo get_the_date();
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
    </div>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}
	
public static function sc_library($atts) {
    // 1. Get all tags (including empty ones like Entrepreneurs)
    $all_tags = get_tags([
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC'
    ]);
    
    // 2. Split tags into two groups
    $skill_tags = []; // For Beginner and Advanced
    $other_tags = []; // For everything else

    foreach ($all_tags as $tag) {
        $name = trim(strtolower($tag->name));
        if ($name === 'beginner' || $name === 'advanced') {
            $skill_tags[] = $tag;
        } else {
            $other_tags[] = $tag;
        }
    }

    // Sort skill tags specifically: Beginner then Advanced
    usort($skill_tags, function($a, $b) {
        return strcasecmp($a->name, 'beginner') === 0 ? -1 : 1;
    });

    // 3. Query all published posts
    $q = new WP_Query([
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => -1,
        'ignore_sticky_posts' => true,
    ]);

    if (!$q->have_posts()) return '<p style="color:#fff; text-align:center;">No articles found.</p>';

    ob_start();
    ?>
    <style>
        .aife-library-wrapper {
            background-color: #1a1c23;
            padding: 60px 20px;
            margin: 40px 0;
            border-radius: 24px;
            color: #fff;
            width: 100%;
            box-sizing: border-box;
        }

        /* Filter Navigation Containers */
        .aife-filter-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            margin-bottom: 50px;
        }

        .aife-filter-row {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        /* Row 1 specific styling (optional) */
        .aife-filter-row.primary .aife-filter-btn {
            padding: 12px 28px;
            font-size: 15px;
        }

        .aife-filter-btn {
            background: #252833;
            border: 1px solid #333745;
            color: #abb2bf;
            padding: 10px 22px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }

        .aife-filter-btn.active, .aife-filter-btn:hover {
            background: #4f46e5;
            color: #fff;
            border-color: #4f46e5;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.4);
        }

        .aife-library-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
            justify-items: center;
        }

        .aife-lib-card {
            background: #252833;
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 380px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .aife-lib-card.hidden { display: none; }

        .aife-lib-card:hover {
            transform: translateY(-8px);
            border-color: #4f46e5;
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
        }

        .aife-lib-image { height: 200px; width: 100%; overflow: hidden; }
        .aife-lib-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .aife-lib-card:hover .aife-lib-image img { transform: scale(1.05); }
    </style>

    <div class="aife-library-wrapper">
        <h2 style="text-align:center; margin-bottom:10px; font-size:36px; color:#fff;">Article Library</h2>
        <p style="text-align:center; color:#abb2bf; margin-bottom:40px;">Browse all our AI resources by category</p>

        <div class="aife-filter-container">
            <!-- Row 1: All, Beginner, Advanced -->
            <div class="aife-filter-row primary">
                <button class="aife-filter-btn active" data-filter="all">All Articles</button>
                <?php foreach ($skill_tags as $tag) : ?>
                    <button class="aife-filter-btn" data-filter="tag-<?php echo $tag->term_id; ?>">
                        <?php echo esc_html($tag->name); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Row 2: Everything else -->
            <div class="aife-filter-row secondary">
                <?php foreach ($other_tags as $tag) : ?>
                    <button class="aife-filter-btn" data-filter="tag-<?php echo $tag->term_id; ?>">
                        <?php echo esc_html($tag->name); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="aife-library-grid">
            <?php while ($q->have_posts()) : $q->the_post(); 
                $post_tags = get_the_tags();
                $tag_classes = '';
                if ($post_tags) {
                    foreach ($post_tags as $t) { $tag_classes .= ' tag-' . $t->term_id; }
                }
            ?>
                <article class="aife-lib-card <?php echo esc_attr($tag_classes); ?>">
                    <div class="aife-lib-image">
                        <a href="<?php the_permalink(); ?>">
                            <?php if (has_post_thumbnail()) : the_post_thumbnail('large'); else : ?>
                                <div style="height:100%; background:#1a1c23; display:flex; align-items:center; justify-content:center; color:#444;">No Image</div>
                            <?php endif; ?>
                        </a>
                    </div>
                    <div style="padding:25px; display:flex; flex-direction:column; flex-grow:1;">
                        <div style="margin-bottom:12px;">
                            <?php if ($post_tags) : ?>
                                <span style="background:#4f46e5; color:#ffffff; font-size:11px; font-weight:700; padding:4px 10px; border-radius:6px; text-transform:uppercase;">
                                    <?php echo esc_html($post_tags[0]->name); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <h3 style="margin:0 0 15px 0; font-size:22px; line-height:1.3;">
                            <a href="<?php the_permalink(); ?>" style="text-decoration:none; color:#ffffff;"><?php the_title(); ?></a>
                        </h3>
                        <div style="font-size:14px; color:#abb2bf; line-height:1.6; margin-bottom:25px; flex-grow:1;">
                            <?php echo wp_trim_words(get_the_excerpt(), 18, '...'); ?>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #333745; padding-top:18px; font-size:12px; color:#7a828e;">
                            <span>By <strong style="color:#fff;"><?php the_author(); ?></strong></span>
                            <span><?php echo get_the_date(); ?></span>
                        </div>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterButtons = document.querySelectorAll('.aife-filter-btn');
        const libraryCards = document.querySelectorAll('.aife-lib-card');

        filterButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                // Update active state across ALL buttons in both rows
                filterButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const selectedFilter = this.getAttribute('data-filter');

                libraryCards.forEach(card => {
                    if (selectedFilter === 'all' || card.classList.contains(selectedFilter)) {
                        card.classList.remove('hidden');
                    } else {
                        card.classList.add('hidden');
                    }
                });
            });
        });
    });
    </script>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}
	
 }

AIFE_Profile_Scoring::init();
