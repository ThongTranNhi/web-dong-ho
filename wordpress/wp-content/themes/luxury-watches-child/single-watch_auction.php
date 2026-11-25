<?php
/**
 * The template for displaying Haute Horlogerie Watch Auctions.
 * Applied to all single watch auctions across Luxury Watches Atelier.
 *
 * @package Luxury_Watches_Child
 * @version 3.0.0
 */

get_header();

while ( have_posts() ) :
    the_post();
    $id = get_the_ID();
    
    // Remove the old filter from the_content so it doesn't duplicate
    if ( class_exists( 'Luxury_Watches_Auctions' ) ) {
        remove_filter( 'the_content', [ 'Luxury_Watches_Auctions', 'auction_content' ] );
    }

    $state = class_exists( 'Luxury_Watches_Auctions' ) ? Luxury_Watches_Auctions::state( $id ) : 'live';
    $state_labels = [
        'live'     => 'Đang diễn ra',
        'upcoming' => 'Sắp mở',
        'ended'    => 'Đã kết thúc',
        'invalid'  => 'Đang cập nhật',
    ];
    $status_label = $state_labels[$state] ?? 'Đang cập nhật';

    // Meta values
    $start_price = absint( get_post_meta( $id, '_lw_start_price', true ) );
    $retail      = absint( get_post_meta( $id, '_lw_retail_price', true ) );
    $increment   = max( 1, absint( get_post_meta( $id, '_lw_bid_increment', true ) ) );
    $reserve     = absint( get_post_meta( $id, '_lw_reserve_price', true ) );
    $lot         = get_post_meta( $id, '_lw_lot_number', true ) ?: 'LOT ' . sprintf( '%03d', $id );
    $gender      = get_post_meta( $id, '_lw_gender', true ) ?: 'Đồng hồ cao cấp';
    $condition   = get_post_meta( $id, '_lw_condition', true ) ?: 'Mới 100% · Đủ hộp và thẻ bảo hành';
    $start_raw   = get_post_meta( $id, '_lw_start_at', true );
    $end_raw     = get_post_meta( $id, '_lw_end_at', true );

    // Timestamps
    $start_ts = ( is_string( $start_raw ) && preg_match( '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}\z/', $start_raw ) ) ? strtotime( $start_raw ) : 0;
    $end_ts   = ( is_string( $end_raw ) && preg_match( '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}\z/', $end_raw ) ) ? strtotime( $end_raw ) : 0;
    $countdown_target = $state === 'upcoming' ? $start_ts : $end_ts;

    // Bid summary & History from database
    global $wpdb;
    $table = $wpdb->prefix . 'luxury_auction_bids';
    $summary_row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(id) as cnt, MAX(amount) as highest FROM {$table} WHERE auction_id = %d", $id ) );
    $bid_count = $summary_row ? absint( $summary_row->cnt ) : 0;
    $highest_bid = $summary_row ? absint( $summary_row->highest ) : 0;
    $current_price = max( $start_price, $highest_bid );
    $minimum_bid = $highest_bid > 0 ? $highest_bid + $increment : $start_price;

    $recent_bids = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, amount, created_at FROM {$table} WHERE auction_id = %d ORDER BY amount DESC, created_at ASC LIMIT 6", $id ) );

    // Image
    $image_url = get_the_post_thumbnail_url( $id, 'full' );
    if ( ! $image_url ) {
        $product_id = absint( get_post_meta( $id, '_lw_product_id', true ) );
        if ( $product_id && function_exists( 'wc_get_product' ) ) {
            $prod = wc_get_product( $product_id );
            if ( $prod && $prod->get_image_id() ) {
                $image_url = wp_get_attachment_image_url( $prod->get_image_id(), 'full' );
            }
        }
    }
    if ( ! $image_url && function_exists( 'wc_placeholder_img_src' ) ) {
        $image_url = wc_placeholder_img_src( 'full' );
    }

    // Bid feedback messages
    $notice = isset( $_GET['bid_status'] ) ? sanitize_key( wp_unslash( $_GET['bid_status'] ) ) : '';
    $messages = [
        'accepted' => [ 'success', 'Lượt trả giá của bạn đã được khớp lệnh thành công. Bạn đang dẫn đầu phiên đấu giá!' ],
        'low'      => [ 'warning', 'Mức giá chưa đạt bước giá tối thiểu hoặc phiên vừa có lượt trả mới. Vui lòng thử lại.' ],
        'closed'   => [ 'warning', 'Phiên đấu giá hiện không nhận giá hoặc đã kết thúc.' ],
        'rate'     => [ 'warning', 'Bạn thao tác quá nhanh. Vui lòng chờ 3 giây rồi thử lại.' ],
        'error'    => [ 'error',   'Không thể lưu lượt trả giá. Vui lòng thử lại.' ],
    ];

    $money = static function ( $val ) {
        return function_exists( 'wc_price' ) ? wc_price( (int) $val ) : number_format_i18n( (int) $val, 0 ) . ' ₫';
    };

    $format_bidder = static function ( $user_id ) {
        if ( (int) $user_id === get_current_user_id() ) {
            return 'Bạn (Đang dẫn đầu)';
        }
        $u = get_userdata( $user_id );
        $name = $u ? trim( $u->display_name ) : '';
        if ( '' === $name ) {
            return 'VIP Member';
        }
        $char = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1, 'UTF-8' ) : substr( $name, 0, 1 );
        return strtoupper( $char ) . '•••' . substr( md5( (string) $user_id ), 0, 2 );
    };
    ?>

    <div class="lw-single-auction-wrapper">
        <div class="lw-single-auction-container">

            <!-- Breadcrumb & Top Bar -->
            <nav class="lw-auction-breadcrumb" aria-label="Đường dẫn trang">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Trang chủ</a>
                <span class="lw-bc-sep">/</span>
                <a href="<?php echo esc_url( home_url( '/dau-gia/' ) ); ?>">Sàn đấu giá live</a>
                <span class="lw-bc-sep">/</span>
                <span class="lw-bc-current"><?php the_title(); ?></span>
            </nav>

            <?php if ( isset( $messages[$notice] ) ) : ?>
                <div class="lw-single-bid-notice lw-notice--<?php echo esc_attr( $messages[$notice][0] ); ?>" role="status">
                    <span class="lw-notice-icon">
                        <?php if ( 'success' === $messages[$notice][0] ) : ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <?php else : ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <?php endif; ?>
                    </span>
                    <div>
                        <strong><?php echo 'success' === $messages[$notice][0] ? 'Xác nhận thành công' : 'Thông báo phiên đấu giá'; ?></strong>
                        <p><?php echo esc_html( $messages[$notice][1] ); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- MAIN 2-COLUMN SPLIT GRID -->
            <div class="lw-single-auction-grid" data-auction-state="<?php echo esc_attr( $state ); ?>">

                <!-- CỘT TRÁI: SHOWCASE ĐỒNG HỒ & THÔNG SỐ -->
                <div class="lw-auction-left-col">
                    
                    <!-- Khung ảnh đồng hồ Showcase -->
                    <div class="lw-auction-hero-gallery">
                        <div class="lw-gallery-badges">
                            <span class="lw-badge-lot"><?php echo esc_html( $lot ); ?></span>
                            <span class="lw-badge-status lw-status--<?php echo esc_attr( $state ); ?>">
                                <span class="lw-pulse-dot"></span>
                                <?php echo esc_html( $status_label ); ?>
                            </span>
                        </div>
                        <div class="lw-gallery-main-image">
                            <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php the_title_attribute(); ?>" loading="eager" />
                        </div>
                        <div class="lw-gallery-caption">
                            <span class="lw-caption-cert">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                                Chứng nhận giám định Atelier Haute Horlogerie 100% chính hãng
                            </span>
                        </div>
                    </div>

                    <!-- Thanh cam kết 3 tiêu chuẩn vàng -->
                    <div class="lw-auction-trust-strip">
                        <div class="lw-trust-strip-item">
                            <div class="lw-trust-strip-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                            </div>
                            <div>
                                <strong>Đền gấp 20 lần</strong>
                                <span>Nếu phát hiện hàng giả</span>
                            </div>
                        </div>
                        <div class="lw-trust-strip-item">
                            <div class="lw-trust-strip-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                            </div>
                            <div>
                                <strong>Bảo hiểm hỏa tốc</strong>
                                <span>Đồng kiểm tận nơi trước nhận</span>
                            </div>
                        </div>
                        <div class="lw-trust-strip-item">
                            <div class="lw-trust-strip-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            </div>
                            <div>
                                <strong>Bảo hành 5 năm</strong>
                                <span>Thay pin &amp; bảo dưỡng trọn đời</span>
                            </div>
                        </div>
                    </div>

                    <!-- Bảng Thông Số Chi Tiết (Specification Card) -->
                    <div class="lw-auction-specs-card">
                        <h2 class="lw-card-section-title">
                            <span>01</span> Hồ Sơ Giám Tuyển Cỗ Máy
                        </h2>
                        <div class="lw-specs-table-grid">
                            <div class="lw-spec-row">
                                <span class="lw-spec-label">Mã định danh phiên (Lot ID)</span>
                                <span class="lw-spec-value"><strong><?php echo esc_html( $lot ); ?></strong></span>
                            </div>
                            <div class="lw-spec-row">
                                <span class="lw-spec-label">Phân khúc &amp; Đối tượng</span>
                                <span class="lw-spec-value"><?php echo esc_html( $gender ); ?></span>
                            </div>
                            <div class="lw-spec-row">
                                <span class="lw-spec-label">Tình trạng vật lý &amp; Phụ kiện</span>
                                <span class="lw-spec-value"><?php echo esc_html( $condition ); ?></span>
                            </div>
                            <?php if ( $retail ) : ?>
                            <div class="lw-spec-row">
                                <span class="lw-spec-label">Giá bán lẻ tham chiếu (Retail)</span>
                                <span class="lw-spec-value"><?php echo wp_kses_post( $money( $retail ) ); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="lw-spec-row">
                                <span class="lw-spec-label">Giá khởi điểm phiên (Starting Bid)</span>
                                <span class="lw-spec-value"><?php echo wp_kses_post( $money( $start_price ) ); ?></span>
                            </div>
                            <div class="lw-spec-row">
                                <span class="lw-spec-label">Bước giá tối thiểu quy định</span>
                                <span class="lw-spec-value">+<?php echo wp_kses_post( $money( $increment ) ); ?></span>
                            </div>
                            <div class="lw-spec-row">
                                <span class="lw-spec-label">Trạng thái giá bảo lưu sàn</span>
                                <span class="lw-spec-value">
                                    <?php if ( ! $reserve || $current_price >= $reserve ) : ?>
                                        <span class="lw-reserve-tag is-met">&#10003; Đã đạt giá bảo lưu (Sẽ chuyển giao)</span>
                                    <?php else : ?>
                                        <span class="lw-reserve-tag is-unmet">&#9679; Đang tiếp cận mức bảo lưu</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Mô tả và Di sản đồng hồ (Story & Heritage) -->
                    <div class="lw-auction-story-card">
                        <h2 class="lw-card-section-title">
                            <span>02</span> Chi Tiết Kiệt Tác &amp; Di Sản
                        </h2>
                        <div class="lw-story-prose">
                            <?php the_content(); ?>
                        </div>
                    </div>

                </div>

                <!-- CỘT PHẢI: BÀN ĐIỀU KHIỂN ĐẤU GIÁ OBSIDIAN GOLD (STICKY CONSOLE) -->
                <div class="lw-auction-right-col">
                    <div class="lw-bidding-console-card">
                        
                        <!-- Header Console -->
                        <div class="lw-console-head">
                            <div class="lw-console-kicker">
                                <span>HAUTE HORLOGERIE AUCTION</span>
                                <span class="lw-lot-pill"><?php echo esc_html( $lot ); ?></span>
                            </div>
                            <h1 class="lw-console-title"><?php the_title(); ?></h1>
                            <p class="lw-console-sub"><?php echo esc_html( $condition ); ?></p>
                        </div>

                        <!-- Khối Đếm Ngược VIP Countdown -->
                        <?php if ( $countdown_target ) : ?>
                            <div class="lw-console-countdown-box">
                                <div class="lw-cd-label-row">
                                    <span class="lw-cd-title">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                        <?php echo $state === 'upcoming' ? 'THỜI GIAN MỞ PHIÊN' : ( $state === 'ended' ? 'PHIÊN ĐÃ KẾT THÚC' : 'THỜI GIAN CÒN LẠI' ); ?>
                                    </span>
                                    <span class="lw-live-indicator"><span class="lw-ping"></span>Khớp lệnh Live</span>
                                </div>
                                <div class="lw-countdown lw-cd-timer-digits" data-end="<?php echo esc_attr( wp_date( DATE_ATOM, $countdown_target ) ); ?>" data-state="<?php echo esc_attr( $state ); ?>">
                                    <div class="lw-cd-display" data-countdown-output>
                                        <?php echo esc_html( wp_date( 'H:i · d/m/Y', $countdown_target ) ); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Khối Báo Giá Hiện Tại (Current Valuation) -->
                        <div class="lw-console-price-panel">
                            <span class="lw-cp-caption">MỨC GIÁ HIỆN TẠI CAO NHẤT</span>
                            <div class="lw-cp-main-price">
                                <?php echo wp_kses_post( $money( $current_price ) ); ?>
                            </div>
                            
                            <div class="lw-cp-meta-grid">
                                <div class="lw-cp-meta-cell">
                                    <span class="lw-meta-name">Giá khởi điểm</span>
                                    <span class="lw-meta-val"><?php echo wp_kses_post( $money( $start_price ) ); ?></span>
                                </div>
                                <div class="lw-cp-meta-cell">
                                    <span class="lw-meta-name">Bước giá tối thiểu</span>
                                    <span class="lw-meta-val">+<?php echo wp_kses_post( $money( $increment ) ); ?></span>
                                </div>
                                <div class="lw-cp-meta-cell">
                                    <span class="lw-meta-name">Tổng lượt trả</span>
                                    <span class="lw-meta-val highlight"><?php echo esc_html( number_format_i18n( $bid_count ) ); ?> lượt</span>
                                </div>
                            </div>
                        </div>

                        <!-- Form Đặt Giá Hoặc Trạng Thái -->
                        <div class="lw-console-action-box">
                            <?php if ( 'live' === $state && is_user_logged_in() ) : ?>
                                <form class="lw-bid-form-custom" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" data-bid-form>
                                    <input type="hidden" name="action" value="luxury_place_bid">
                                    <input type="hidden" name="auction_id" value="<?php echo esc_attr( $id ); ?>">
                                    <?php wp_nonce_field( 'luxury_bid_' . $id, 'luxury_bid_nonce' ); ?>
                                    
                                    <div class="lw-bid-input-wrap">
                                        <label for="luxury-bid-amount">Nhập mức giá bạn muốn trả (VND)</label>
                                        <div class="lw-bid-input-row">
                                            <input id="luxury-bid-amount" name="amount" type="number" inputmode="numeric" 
                                                   min="<?php echo esc_attr( $minimum_bid ); ?>" 
                                                   step="<?php echo esc_attr( $increment ); ?>" 
                                                   value="<?php echo esc_attr( $minimum_bid ); ?>" 
                                                   required aria-describedby="luxury-bid-hint">
                                            <span class="lw-bid-currency">VND</span>
                                        </div>
                                    </div>

                                    <!-- Quick Preset Increment Buttons -->
                                    <div class="lw-quick-presets" aria-label="Mức giá chọn nhanh">
                                        <span class="lw-presets-title">Chọn nhanh:</span>
                                        <?php 
                                        foreach ( [ 1, 2, 5 ] as $step_mult ) :
                                            $sugg = $minimum_bid + ( $increment * ( $step_mult - 1 ) );
                                            ?>
                                            <button type="button" class="lw-preset-btn" data-bid-value="<?php echo esc_attr( $sugg ); ?>">
                                                +<?php echo esc_html( number_format_i18n( $increment * $step_mult ) ); ?> ₫
                                            </button>
                                        <?php endforeach; ?>
                                    </div>

                                    <button type="submit" class="lw-bid-submit-btn">
                                        <span>XÁC NHẬN TRẢ GIÁ NGAY</span>
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                                    </button>
                                    
                                    <p class="lw-bid-hint-text" id="luxury-bid-hint">
                                        * Mức giá tối thiểu hợp lệ là <strong><?php echo wp_kses_post( $money( $minimum_bid ) ); ?></strong>. Giao dịch được mã hóa và bảo mật chống trùng lặp theo thời gian thực.
                                    </p>
                                </form>

                            <?php elseif ( 'live' === $state ) : ?>
                                <div class="lw-console-login-cta">
                                    <div class="lw-login-cta-head">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                                        <strong>Đăng Nhập Thành Viên VIP</strong>
                                    </div>
                                    <p>Quý khách vui lòng đăng nhập để xác thực danh tính người trả giá và tham gia phiên đấu giá độc quyền.</p>
                                    <a class="lw-console-login-btn" href="<?php echo esc_url( wp_login_url( get_permalink( $id ) ) ); ?>">
                                        ĐĂNG NHẬP ĐỂ TRẢ GIÁ NGAY
                                    </a>
                                </div>

                            <?php elseif ( 'upcoming' === $state ) : ?>
                                <div class="lw-console-status-card upcoming">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    <strong>Phiên Đấu Giá Sắp Mở</strong>
                                    <p>Cỗ máy thời gian sẽ bắt đầu nhận mức trả giá vào lúc <strong><?php echo esc_html( wp_date( 'H:i, d/m/Y', $start_ts ) ); ?></strong>. Quý khách có thể đăng nhập trước để chuẩn bị.</p>
                                </div>

                            <?php else : ?>
                                <div class="lw-console-status-card ended">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    <strong>Phiên Đấu Giá Đã Khép Lại</strong>
                                    <p>Mức giá gõ búa chung cuộc ghi nhận là <strong><?php echo wp_kses_post( $money( $current_price ) ); ?></strong>. Chân thành cảm ơn các nhà sưu tầm đã tham gia.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Bảng Lịch Sử Khớp Lệnh Trực Tiếp (Live Bid History) -->
                        <div class="lw-console-history-box">
                            <div class="lw-history-head">
                                <h3>LỊCH SỬ TRẢ GIÁ GẦN NHẤT</h3>
                                <span class="lw-history-badge"><?php echo esc_html( number_format_i18n( count( $recent_bids ) ) ); ?> lượt</span>
                            </div>

                            <?php if ( ! empty( $recent_bids ) ) : ?>
                                <ul class="lw-history-list">
                                    <?php foreach ( $recent_bids as $idx => $bid ) : 
                                        $rank = $idx + 1;
                                        ?>
                                        <li class="lw-history-item <?php echo 1 === $rank ? 'is-leader' : ''; ?>">
                                            <span class="lw-history-rank rank-<?php echo esc_attr( $rank ); ?>">#<?php echo esc_html( $rank ); ?></span>
                                            <div class="lw-history-user">
                                                <strong><?php echo esc_html( $format_bidder( $bid->user_id ) ); ?></strong>
                                                <time datetime="<?php echo esc_attr( mysql2date( DATE_ATOM, $bid->created_at ) ); ?>">
                                                    <?php echo esc_html( mysql2date( 'H:i:s · d/m', $bid->created_at ) ); ?>
                                                </time>
                                            </div>
                                            <div class="lw-history-amount">
                                                <?php echo wp_kses_post( $money( $bid->amount ) ); ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <div class="lw-history-empty">
                                    <p>Chưa có lượt trả giá nào được ghi nhận.<br>Hãy là nhà sưu tầm đầu tiên mở nhịp cho phiên này!</p>
                                </div>
                            <?php endif; ?>

                            <div class="lw-history-footer">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                <span>Bảo mật danh tính &amp; Giao dịch minh bạch chuẩn quốc tế</span>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <!-- PHẦN DƯỚI: CÁC PHIÊN ĐẤU GIÁ KHÁC ĐANG DIỄN RA -->
            <?php
            $other_auctions = new WP_Query([
                'post_type'      => 'watch_auction',
                'post_status'    => 'publish',
                'post__not_in'   => [ $id ],
                'posts_per_page' => 3,
                'meta_key'       => '_lw_end_at',
                'orderby'        => [ 'meta_value' => 'ASC', 'date' => 'DESC' ],
            ]);
            if ( $other_auctions->have_posts() && class_exists( 'Luxury_Watches_Auctions' ) ) :
            ?>
            <section class="lw-single-related-auctions">
                <div class="lw-related-head">
                    <span class="lw-related-kicker">BỘ SƯU TẬP TUYỂN CHỌN</span>
                    <h2>Các Phiên Đấu Giá Nổi Bật Khác</h2>
                </div>
                <div class="lw-auction-grid">
                    <?php 
                    while ( $other_auctions->have_posts() ) :
                        $other_auctions->the_post();
                        echo Luxury_Watches_Auctions::auction_card( get_the_ID() );
                    endwhile;
                    wp_reset_postdata();
                    ?>
                </div>
            </section>
            <?php endif; ?>

        </div>
    </div>

<?php
endwhile;

get_footer();
