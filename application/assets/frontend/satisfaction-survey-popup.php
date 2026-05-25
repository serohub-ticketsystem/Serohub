<!-- Zufriedenheitsumfrage Popup -->
<div id="satisfaction-survey-popup" class="hidden fixed inset-0 z-[60] overflow-y-auto" aria-labelledby="survey-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-black/50 dark:bg-black/70 transition-opacity" aria-hidden="true" id="surveyOverlay"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6 text-center">
            <!-- Schritt 1: Bewertung -->
            <div id="survey-step-rating">
                <h2 id="survey-title" class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Zufriedenheitsumfrage</h2>
                <p id="survey-question" class="text-gray-700 dark:text-gray-300 mb-6">Wie zufrieden sind Sie mit unserem Service?</p>
                <div class="flex justify-center gap-3 flex-wrap mb-4">
                    <button type="button" class="survey-rating flex flex-col items-center gap-1 p-3 rounded-lg border-2 border-gray-200 dark:border-gray-600 hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 dark:hover:border-primary-500 transition-colors" data-rating="1" title="1 - Sehr unzufrieden">
                        <span class="text-3xl">😞</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">1</span>
                    </button>
                    <button type="button" class="survey-rating flex flex-col items-center gap-1 p-3 rounded-lg border-2 border-gray-200 dark:border-gray-600 hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 dark:hover:border-primary-500 transition-colors" data-rating="2" title="2 - Unzufrieden">
                        <span class="text-3xl">😕</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">2</span>
                    </button>
                    <button type="button" class="survey-rating flex flex-col items-center gap-1 p-3 rounded-lg border-2 border-gray-200 dark:border-gray-600 hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 dark:hover:border-primary-500 transition-colors" data-rating="3" title="3 - Neutral">
                        <span class="text-3xl">😐</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">3</span>
                    </button>
                    <button type="button" class="survey-rating flex flex-col items-center gap-1 p-3 rounded-lg border-2 border-gray-200 dark:border-gray-600 hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 dark:hover:border-primary-500 transition-colors" data-rating="4" title="4 - Zufrieden">
                        <span class="text-3xl">😊</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">4</span>
                    </button>
                    <button type="button" class="survey-rating flex flex-col items-center gap-1 p-3 rounded-lg border-2 border-gray-200 dark:border-gray-600 hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 dark:hover:border-primary-500 transition-colors" data-rating="5" title="5 - Sehr zufrieden">
                        <span class="text-3xl">😄</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">5</span>
                    </button>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">1 = Sehr unzufrieden · 5 = Sehr zufrieden</p>
                <button type="button" id="survey-ask-later" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 underline">
                    Später fragen
                </button>
            </div>
            <!-- Schritt 2: Verbesserungsvorschlag (nur bei 1–3) -->
            <div id="survey-step-feedback" class="hidden text-left">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Vielen Dank für Ihre Bewertung</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Was könnten wir verbessern? (optional)</p>
                <textarea id="survey-feedback-input" rows="4" maxlength="2000" placeholder="Ihre Anmerkungen helfen uns, unseren Service zu verbessern …"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:placeholder-gray-400 mb-4 resize-none"></textarea>
                <div class="flex gap-2 justify-end">
                    <button type="button" id="survey-skip-feedback" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 dark:text-gray-300">
                        Überspringen
                    </button>
                    <button type="button" id="survey-submit-feedback" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700">
                        Absenden
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let surveySelectedRating = 0;
let surveyCurrentId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadSatisfactionSurvey();
});

async function loadSatisfactionSurvey() {
    const popup = document.getElementById('satisfaction-survey-popup');
    if (!popup) return;

    try {
        const b = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>');
        const response = await fetch(b + 'admin/api/satisfaction-survey.php?public=true');
        const data = await response.json();

        if (data.success && data.survey) {
            const dismissedUntil = parseInt(localStorage.getItem('satisfaction_survey_ask_later') || '0', 10);
            if (dismissedUntil > Date.now()) {
                popup.classList.add('hidden');
                return;
            }
            surveyCurrentId = data.survey.id;
            const questionEl = document.getElementById('survey-question');
            if (questionEl) questionEl.textContent = data.survey.frage;
            document.getElementById('survey-step-rating').classList.remove('hidden');
            document.getElementById('survey-step-feedback').classList.add('hidden');
            popup.classList.remove('hidden');
        } else {
            popup.classList.add('hidden');
        }
    } catch (error) {
        console.error('Fehler beim Laden der Zufriedenheitsumfrage:', error);
        popup.classList.add('hidden');
    }
}

function closeSatisfactionSurvey() {
    const popup = document.getElementById('satisfaction-survey-popup');
    if (popup) popup.classList.add('hidden');
}

async function submitSurvey(rating, feedback) {
    const b = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>');
    try {
        const body = { rating: rating };
        if (surveyCurrentId) body.survey_id = surveyCurrentId;
        if (feedback) body.feedback = feedback;
        const response = await fetch(b + 'api/satisfaction-survey-response.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await response.json();
        if (data.success) {
            closeSatisfactionSurvey();
            if (typeof showToast === 'function') showToast('Vielen Dank für Ihre Bewertung!', 'success');
        }
    } catch (err) {
        console.error('Fehler beim Senden der Bewertung:', err);
    }
}

// Rating-Klick
document.addEventListener('click', async function(e) {
    const btn = e.target.closest('.survey-rating');
    if (!btn) return;

    const rating = parseInt(btn.dataset.rating, 10);
    if (isNaN(rating) || rating < 1 || rating > 5) return;

    surveySelectedRating = rating;

    if (rating >= 4) {
        await submitSurvey(rating, null);
        return;
    }

    // Rating 1–3: Folge-Frage anzeigen
    document.getElementById('survey-step-rating').classList.add('hidden');
    document.getElementById('survey-step-feedback').classList.remove('hidden');
    document.getElementById('survey-feedback-input').value = '';
    document.getElementById('survey-feedback-input').focus();
});

document.getElementById('survey-skip-feedback')?.addEventListener('click', function() {
    submitSurvey(surveySelectedRating, null);
});

document.getElementById('survey-submit-feedback')?.addEventListener('click', function() {
    const feedback = document.getElementById('survey-feedback-input')?.value.trim() || null;
    submitSurvey(surveySelectedRating, feedback);
});

document.getElementById('survey-ask-later')?.addEventListener('click', function() {
    localStorage.setItem('satisfaction_survey_ask_later', String(Date.now() + 24 * 60 * 60 * 1000));
    closeSatisfactionSurvey();
});
</script>
