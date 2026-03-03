<?php
// Requires $page_section to be set before including (e.g., 'Research', 'Academic')
if (!isset($page_section)) {
    $page_section = 'General';
}
?>

<div id="aiJourneyWidget" style="background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%); border: 1px solid #dcdde1; border-radius: 12px; padding: 20px; margin-bottom: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; gap: 20px; align-items: flex-start; position: relative; overflow: hidden;">
    
    <!-- Decorative side accent -->
    <div style="position: absolute; left: 0; top: 0; bottom: 0; width: 6px; background: linear-gradient(to bottom, #8e44ad, #3498db);"></div>

    <!-- AI Avatar / Icon -->
    <div style="background: white; border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.1); flex-shrink: 0;">
        <i class="fas fa-robot" style="font-size: 2em; color: #8e44ad;"></i>
    </div>

    <!-- Content Area -->
    <div style="flex-grow: 1;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <h4 style="margin: 0; color: #2c3e50; font-size: 1.15em; display: flex; align-items: center; gap: 8px;">
                Mallika's <?php echo htmlspecialchars($page_section); ?> Guide
                <span id="journeyPhaseBox" style="background: #e8eaed; color: #5f6368; font-size: 0.65em; padding: 4px 12px; border-radius: 20px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;">
                    <i class="fas fa-spinner fa-spin"></i> Analyzing...
                </span>
            </h4>
        </div>
        
        <div id="journeyAnalysisText" style="color: #444; font-size: 0.95em; line-height: 1.5; margin-bottom: 12px; min-height: 42px;">
            Loading your personalized <?php echo strtolower(htmlspecialchars($page_section)); ?> journey based on your recent activity...
        </div>

        <div style="background: #f0f4f8; border-left: 3px solid #3498db; padding: 10px 14px; border-radius: 4px;">
            <strong style="color: #2980b9; font-size: 0.85em; text-transform: uppercase;">Next Recommended Step:</strong>
            <div id="journeyNextStep" style="color: #2c3e50; font-size: 0.9em; margin-top: 4px; font-weight: 500;">
                <i class="fas fa-ellipsis-h" style="color: #bdc3c7;"></i>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const section = '<?php echo addslashes($page_section); ?>';
    
    fetch('ajax_journey_guide.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ section_name: section })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.guide) {
            const guide = data.guide;
            
            // Format the phase badge
            const phaseSpan = document.getElementById('journeyPhaseBox');
            phaseSpan.innerHTML = '<i class="fas fa-map-marker-alt" style="margin-right:4px;"></i>' + (guide.current_phase || 'Phase Unknown');
            phaseSpan.style.background = '#8e44ad';
            phaseSpan.style.color = 'white';
            
            document.getElementById('journeyAnalysisText').innerHTML = guide.analysis || 'Keep up the good work and log your daily activities to get better insights.';
            document.getElementById('journeyNextStep').innerHTML = guide.next_step || 'Continue your normal tasks.';
        } else {
            document.getElementById('journeyPhaseBox').innerHTML = 'Ready';
            document.getElementById('journeyAnalysisText').innerHTML = 'Please add a Daily Activity log for this section to receive personalized guidance.';
            document.getElementById('journeyNextStep').innerHTML = 'Update your activity log today.';
        }
    })
    .catch(error => {
        console.error('Error fetching AI journey:', error);
        document.getElementById('journeyPhaseBox').innerHTML = 'Offline';
        document.getElementById('journeyAnalysisText').innerHTML = 'AI Guide is currently offline.';
        document.getElementById('journeyNextStep').innerHTML = '-';
    });
});
</script>
