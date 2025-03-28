<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
    <div class="bg-primary-700 text-white px-4 py-3 flex justify-between items-center">
        <h2 class="font-semibold text-lg">Sit-In History</h2>
    </div>
    
    <div class="p-4">
        <?php if (empty($sitInHistory)): ?>
            <div class="text-center py-8">
                <i class="fas fa-history text-gray-300 text-5xl mb-3"></i>
                <p class="text-gray-500 mb-1">No sit-in history found</p>
                <p class="text-sm text-gray-400">Your completed sit-in sessions will appear here</p>
            </div>
        <?php else: ?>
            <?php foreach ($groupedSitIns as $month => $sitIns): ?>
                <div class="mb-6">
                    <h3 class="text-md font-medium text-gray-700 mb-3 flex items-center">
                        <i class="far fa-calendar-alt mr-2 text-primary-500"></i>
                        <?php echo htmlspecialchars($month); ?>
                    </h3>
                    
                    <div class="space-y-4">
                        <?php foreach ($sitIns as $sitIn): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-sm transition relative">
                                <div class="flex flex-wrap justify-between items-start">
                                    <div>
                                        <div class="flex items-center mb-1">
                                            <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded-full mr-2">
                                                <?php echo htmlspecialchars($sitIn['lab_name']); ?>
                                            </span>
                                            <?php if ($sitIn['computer_number']): ?>
                                                <span class="bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                                                    PC #<?php echo htmlspecialchars($sitIn['computer_number']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <h4 class="font-medium text-gray-900">
                                            <?php echo htmlspecialchars($sitIn['purpose']); ?>
                                        </h4>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <div class="flex items-center">
                                            <i class="far fa-clock mr-1"></i>
                                            <?php echo date('M d, Y g:i A', strtotime($sitIn['check_in_time'])); ?>
                                            <span class="mx-1">→</span>
                                            <?php echo date('g:i A', strtotime($sitIn['check_out_time'])); ?>
                                        </div>
                                        <?php
                                            $start = new DateTime($sitIn['check_in_time']);
                                            $end = new DateTime($sitIn['check_out_time']);
                                            $interval = $start->diff($end);
                                            
                                            $duration = '';
                                            if ($interval->h > 0) {
                                                $duration .= $interval->h . ' hour' . ($interval->h > 1 ? 's' : '');
                                            }
                                            if ($interval->i > 0) {
                                                if ($duration) $duration .= ' ';
                                                $duration .= $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
                                            }
                                        ?>
                                        <div class="text-xs text-gray-400">Duration: <?php echo $duration ?: 'Less than a minute'; ?></div>
                                    </div>
                                </div>
                                
                                <div class="mt-3 flex justify-end">
                                    <?php if ($sitIn['has_feedback'] > 0): ?>
                                        <span class="text-xs text-green-600 flex items-center">
                                            <i class="fas fa-check-circle mr-1"></i> Feedback submitted
                                        </span>
                                    <?php else: ?>
                                        <a href="history.php?feedback=<?php echo $sitIn['session_id']; ?>" 
                                           class="text-xs bg-primary-600 text-white px-3 py-1 rounded hover:bg-primary-700 transition inline-flex items-center">
                                            <i class="far fa-comment-alt mr-1"></i> Add Feedback
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Feedback Form Modal -->
<?php if ($feedbackSession): ?>
<div id="feedbackModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="bg-primary-700 text-white px-4 py-3 rounded-t-lg flex justify-between items-center">
            <h3 class="font-semibold">Submit Feedback</h3>
            <a href="history.php" class="text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </a>
        </div>
        
        <div class="p-6">
            <div class="mb-4 bg-blue-50 p-3 rounded-lg text-sm">
                <p class="text-blue-800 flex items-start">
                    <i class="fas fa-info-circle mt-1 mr-2"></i>
                    <span>Your feedback helps us improve the lab sit-in experience. Thank you for taking the time to share your thoughts!</span>
                </p>
            </div>
            
            <form action="../admin/submit_feedback.php" method="POST">
                <input type="hidden" name="session_id" value="<?php echo $feedbackSession['session_id']; ?>">
                <input type="hidden" name="return_to" value="user/history.php">
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-medium mb-2">How would you rate your sit-in experience?</label>
                    <div class="flex items-center space-x-2 rating-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <label class="cursor-pointer text-2xl">
                            <input type="radio" name="rating" value="<?php echo $i; ?>" class="sr-only">
                            <i class="far fa-star" data-rating="<?php echo $i; ?>"></i>
                        </label>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div class="mb-5">
                    <label for="feedback" class="block text-gray-700 text-sm font-medium mb-2">Your Feedback</label>
                    <textarea id="feedback" name="feedback" rows="4" placeholder="Please share your experience, suggestions or any issues you encountered..." 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"></textarea>
                </div>
                
                <div class="flex justify-end">
                    <a href="history.php" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 mr-3 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">
                        Submit Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Star rating functionality
    document.addEventListener('DOMContentLoaded', function() {
        const stars = document.querySelectorAll('.rating-stars i');
        
        // Function to update stars
        function updateStars(selectedRating) {
            stars.forEach(star => {
                const rating = parseInt(star.dataset.rating);
                if (rating <= selectedRating) {
                    star.classList.remove('far', 'fa-star');
                    star.classList.add('fas', 'fa-star', 'text-yellow-400');
                } else {
                    star.classList.remove('fas', 'fa-star', 'text-yellow-400');
                    star.classList.add('far', 'fa-star');
                }
            });
        }
        
        // Add click events to stars
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.dataset.rating);
                updateStars(rating);
                this.previousElementSibling.checked = true;
            });
            
            // Hover effects
            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.dataset.rating);
                
                stars.forEach(s => {
                    const r = parseInt(s.dataset.rating);
                    if (r <= rating) {
                        s.classList.add('hover:text-yellow-500');
                    }
                });
            });
            
            star.addEventListener('mouseleave', function() {
                stars.forEach(s => {
                    s.classList.remove('hover:text-yellow-500');
                });
            });
        });
    });
</script>
<?php endif; ?>
