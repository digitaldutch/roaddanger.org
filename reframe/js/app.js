class ReFrameApp {
    constructor() {
        // State management
        this.isAnalyzing = false;
        this.analysis = null;

        // Interactive state for criteria
        this.expandedOriginalCriteria = [];
        this.expandedHumanizedCriteria = [];
        this.isGenerating = false;
        
        // DOM elements
        this.form = document.getElementById('headline-form');
        this.headlineInputEl = document.getElementById('headline');
        this.articleBodyInputEl = document.getElementById('article-body');
        this.analyzeBtn = document.getElementById('analyze-btn');
        this.analyzeText = document.getElementById('analyze-text');
        this.analyzeLoading = document.getElementById('analyze-loading');
        this.formContainer = document.getElementById('form-container');
        this.resultsContainer = document.getElementById('results-container');
        this.analysisResults = document.getElementById('analysis-results');
        this.resetBtn = document.getElementById('reset-btn');
        this.exampleBtns = document.querySelectorAll('.example-btn');
        this.tooltip = document.getElementById('tooltip');
        this.tooltipText = document.getElementById('tooltip-text');
        this.downloadLink = document.getElementById('download-link');
        
        this.initializeEventListeners();
        this.updateSubmitButton();
        this.loadArticleFromURL();
    }
    
    initializeEventListeners() {
        // Example article selection
        this.exampleBtns.forEach(btn => {
            btn.addEventListener('click', () => this.handleExampleSelect(btn));
        });
        
        // Form submission
        this.form.addEventListener('submit', (e) => this.handleAnalyze(e));
        
        // Reset button
        this.resetBtn.addEventListener('click', (e) => this.resetAnalysis(e));
        
        // Input validation
        this.headlineInputEl.addEventListener('input', () => {
            this.headlineInput = this.headlineInputEl.value;
            this.updateSubmitButton();
        });
        
        this.articleBodyInputEl.addEventListener('input', () => {
            this.articleBodyInput = this.articleBodyInputEl.value;
            this.updateSubmitButton();
        });
        
        // Global tooltip handlers
        document.addEventListener('mouseover', (e) => this.handleTooltipShow(e));
        document.addEventListener('mouseout', (e) => this.handleTooltipHide(e));
    }
    
    // Handle example selection
    handleExampleSelect(btn) {
        const headline = btn.getAttribute('data-headline');
        const body = btn.getAttribute('data-body').replace(/&quot;/g, '"');
        
        this.headlineInput = headline;
        this.articleBodyInput = body;
        this.headlineInputEl.value = headline;
        this.articleBodyInputEl.value = body;
        
        // Clear previous analysis when a new example is selected
        this.analysis = null;
        this.hideResults();
        this.updateSubmitButton();
    }
    
    // Handle form submission
    async handleAnalyze(event) {
        event.preventDefault();
        
        const headline = this.headlineInputEl.value.trim();
        const articleBody = this.articleBodyInputEl.value.trim();
        
        if (!headline || !articleBody || this.isAnalyzing) {
            return;
        }
        
        this.setAnalyzing(true);
        
        // Clear previous analysis to ensure UI updates to the loading state
        if (this.analysis !== null) {
            this.analysis = null;
            this.hideResults();
            await this.tick(); // Allow DOM to clear previous results
        }
        
        try {
            const response = await fetch('ReframeHandler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    function: 'analyzeArticle',
                    headline: headline,
                    articleBody: articleBody
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            this.analysis = data.analysis;
            
            // After analysis is set, show results
            await this.tick();
            
            if (this.analysis) {
                await this.displayResults();
                this.showResults();
                this.scrollToTop();
            }
            
        } catch (error) {
            console.error('Analysis failed:', error);
            this.displayError(error.message);
        } finally {
            this.setAnalyzing(false);
        }
    }
    
    // Reset analysis
    resetAnalysis(event) {
        event.preventDefault();
        this.analysis = null;
        this.headlineInput = '';
        this.articleBodyInput = '';
        this.headlineInputEl.value = '';
        this.articleBodyInputEl.value = '';
        this.hideResults();
        this.updateSubmitButton();
        this.scrollToTop();
    }
    
    // UI state management
    setAnalyzing(analyzing) {
        this.isAnalyzing = analyzing;
        this.analyzeBtn.disabled = analyzing;
        
        if (analyzing) {
            this.analyzeText.classList.add('hidden');
            this.analyzeLoading.classList.remove('hidden');
        } else {
            this.analyzeText.classList.remove('hidden');
            this.analyzeLoading.classList.add('hidden');
        }
        
        this.updateSubmitButton();
    }
    
    showResults() {
        this.formContainer.classList.add('hidden');
        this.resultsContainer.classList.remove('hidden');
    }
    
    hideResults() {
        this.formContainer.classList.remove('hidden');
        this.resultsContainer.classList.add('hidden');
    }
    
    updateSubmitButton() {
        const headline = this.headlineInputEl.value.trim();
        const articleBody = this.articleBodyInputEl.value.trim();
        this.analyzeBtn.disabled = !headline || !articleBody || this.isAnalyzing;
    }

    async loadArticleFromURL() {
        const urlParams = new URLSearchParams(window.location.search);
        const articleId = urlParams.get('articleId');

        if (articleId) {
            const response = await fetch('ReframeHandler.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    function: 'getArticle',
                    articleId: articleId,
                })
            });

            const data = await response.json();

            if (data.error) {
               alert(data.error);
               return;
            }

            this.headlineInputEl.value = data.title;
            this.articleBodyInputEl.value = data.text;

            this.updateSubmitButton();

            this.analyzeBtn.click();
        }
    }
    
    scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    async tick() {
        return new Promise(resolve => requestAnimationFrame(resolve));
    }
    
    async displayResults() {
        if (!this.analysis.isRelevant) {
            this.displayNonRelevantResult();
            return;
        }
        
        // Get highlighted headlines asynchronously
        const originalHighlighted = await this.createHighlightedHeadline(this.analysis.originalHeadline, true, !this.isGenerating);
        const improvedHighlighted = await this.createHighlightedHeadline(this.analysis.improvedHeadline, false, !this.isGenerating);
        
        // Create the side-by-side comparison
        this.analysisResults.innerHTML = `
            <div>
                <h2 class="text-4xl font-normal text-black mb-2">Results:</h2>
                
                <!-- Image Generator Component -->
                <div class="mt-6">
                    <div id="image-render-source" class="bg-white border-8 border-black font-sans">
                        <div class="flex flex-col md:flex-row w-full">
                            <!-- Left Column: Original - Red background with white text -->
                            <div class="w-full md:w-1/2 bg-red-600 px-6 py-4 md:p-12 border-b-2 md:border-b-0 md:border-r-2 border-black">
                                <h3 class="text-md sm:text-2xl md:text-3xl font-black text-white mb-2 md:mb-6 uppercase">Original</h3>
                                <p class="text-md sm:text-3xl md:text-3xl text-white font-semibold md:font-bold leading-tight break-words">
                                    ${originalHighlighted}
                                </p>
                                
                                <!-- Criteria list for original headline -->
                                <div class="mt-6 space-y-5">
                                    ${this.createOriginalCriteriaHTML()}
                                </div>
                            </div>
                            
                            <!-- Right Column: Humanized - White background with black text -->
                            <div class="w-full md:w-1/2 bg-white px-6 py-4 md:p-12 md:pt-8">
                                <p class="text-sm text-black -mb-1 font-light">AI-rewritten</p>
                                <h3 class="text-md sm:text-2xl md:text-3xl font-black text-black mb-2 md:mb-6 uppercase">Humanized</h3>
                                <p class="text-md sm:text-3xl md:text-3xl text-black font-semibold md:font-bold leading-tight break-words">
                                    ${improvedHighlighted}
                                </p>
                                
                                <!-- Criteria list for humanized headline - all have checkmarks -->
                                <div class="mt-6 space-y-5">
                                    ${this.createHumanizedCriteriaHTML()}
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-black p-4 text-white flex justify-between items-center">
                            <div class="text-sm md:text-lg">reframe-dev.pages.dev</div>
                            <div class="text-sm md:text-lg">tell the <span class="text-amber-500">human</span> story</div>
                        </div>
                    </div>
                    
                    <div class="p-4 text-center">
                        <button id="download-image-btn" onclick="app.downloadGeneratedImage()" 
                                class="px-8 py-3 bg-white text-xl text-black font-normal border-2 border-black hover:bg-red-600 hover:text-white hover:border-white transition-all disabled:opacity-75 disabled:cursor-not-allowed"
                                ${this.isGenerating ? 'disabled' : ''}>
                            ${this.isGenerating ? `
                                <span class="flex items-center justify-center">
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Generating...
                                </span>
                            ` : 'Download Image'}
                        </button>
                    </div>
                </div>
            </div>
        `;
        
    }
    
    // Create highlighted headline HTML
    async createHighlightedHeadline(headline, isDarkBackground, showTooltips = true) {
        if (!showTooltips) return this.escapeHtml(headline);
        
        try {
            const response = await fetch('highlight.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    headline: headline,
                    isDarkBackground: isDarkBackground,
                    showTooltips: showTooltips
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            return data.highlightedHTML;
            
        } catch (error) {
            console.error('Highlighting failed:', error);
            // Fallback to escaped HTML if highlighting fails
            return this.escapeHtml(headline);
        }
    }
    
    // Create original criteria HTML
    createOriginalCriteriaHTML() {
        const criteriaNames = ['Mention all parties involved', 'Uses human terms', 'Active voice'];
        let html = '';
        
        for (let i = 1; i <= 3; i++) {
            const passed = this.isCriterionMetInOriginal(i);
            const disabled = this.isOriginalCriterionDisabled(i);
            const expanded = this.expandedOriginalCriteria.includes(i);
            
            html += `
                <div>
                    <button class="w-full flex items-center text-left ${!disabled ? 'cursor-pointer' : 'opacity-50 cursor-not-allowed'}" 
                         onclick="${!disabled ? `app.toggleOriginalCriterion(${i})` : ''}">
                        <div class="w-8 h-8 flex items-center justify-center border border-white ${disabled ? 'bg-transparent' : passed ? 'bg-white' : 'bg-transparent'}">
                            ${!disabled && passed ? `
                                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                </svg>
                            ` : !disabled ? `
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            ` : '<span class="text-xs text-white">-</span>'}
                        </div>
                        <span class="ml-3 text-white">${criteriaNames[i-1]}</span>
                        ${!disabled ? `
                            <svg class="w-5 h-5 ml-auto text-white transform transition-transform duration-200 ${expanded ? 'rotate-180' : ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        ` : ''}
                    </button>
                    
                    ${!disabled && expanded ? `
                        <div class="pl-11 mt-2 text-white text-sm border-l-2 border-white ml-4">
                            ${!passed ? this.getOriginalExplanationForCriterion(i) : 'This criterion is met in the original headline.'}
                        </div>
                    ` : ''}
                </div>
            `;
        }
        
        return html;
    }
    
    // Create humanized criteria HTML
    createHumanizedCriteriaHTML() {
        const criteriaNames = ['Mention all parties involved', 'Uses human terms', 'Active voice'];
        let html = '';
        
        for (let i = 1; i <= 3; i++) {
            const expanded = this.expandedHumanizedCriteria.includes(i);
            
            html += `
                <div>
                    <button class="w-full flex items-center text-left cursor-pointer" 
                            onclick="app.toggleHumanizedCriterion(${i})">
                        <div class="w-8 h-8 flex items-center justify-center border border-black bg-red-600">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <span class="ml-3 text-black">${criteriaNames[i-1]}</span>
                        <svg class="w-5 h-5 ml-auto text-black transform transition-transform duration-200 ${expanded ? 'rotate-180' : ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    
                    ${expanded ? `
                        <div class="pl-11 mt-2 text-black text-sm border-l-2 border-black ml-4">
                            ${this.analysis.changes && this.analysis.changes.some(c => c.criterionId === i) ? this.getHumanizedExplanationForCriterion(i) : `${criteriaNames[i-1]} is properly used in this headline.`}
                        </div>
                    ` : ''}
                </div>
            `;
        }
        
        return html;
    }
    
    // Criteria interaction methods
    async toggleOriginalCriterion(criterionId) {
        if (this.isOriginalCriterionDisabled(criterionId)) return;
        
        const index = this.expandedOriginalCriteria.indexOf(criterionId);
        if (index === -1) {
            this.expandedOriginalCriteria.push(criterionId);
        } else {
            this.expandedOriginalCriteria.splice(index, 1);
        }
        
        await this.displayResults(); // Re-render
    }
    
    async toggleHumanizedCriterion(criterionId) {
        const index = this.expandedHumanizedCriteria.indexOf(criterionId);
        if (index === -1) {
            this.expandedHumanizedCriteria.push(criterionId);
        } else {
            this.expandedHumanizedCriteria.splice(index, 1);
        }
        
        await this.displayResults(); // Re-render
    }
    
    // Helper methods for criteria
    isCriterionMetInOriginal(criterionId) {
        const criteria = this.analysis.criteriaResults.find(c => c.criterionId === criterionId);

        return criteria && criteria.passed;
    }
    
    isOriginalCriterionDisabled(criterionId) {
        if (criterionId === 1) return false;
        if (criterionId === 2) return !this.isCriterionMetInOriginal(1);
        if (criterionId === 3) return !this.isCriterionMetInOriginal(1) || !this.isCriterionMetInOriginal(2);
        return false;
    }
    
    getOriginalExplanationForCriterion(criterionId) {
        const criterion = this.analysis.criteriaResults.find(c => c.criterionId === criterionId);
        return criterion ? criterion.explanation : 'This criterion is met in the original headline.';
    }
    
    getHumanizedExplanationForCriterion(criterionId) {
        const change = this.analysis.changes.find(c => c.criterionId === criterionId);
        return change ? change.explanation : 'This criterion is met in the original headline.';
    }
    

    // Image generation
    async downloadGeneratedImage() {
        const elementToCapture = document.getElementById('image-render-source');
        if (!elementToCapture) {
            console.error('Element to capture not found for image generation.');
            alert('Error: Could not find content to generate image from.');
            return;
        }

        const downloadBtn = document.getElementById('download-image-btn');

        this.isGenerating = true;
        if (downloadBtn) {
            downloadBtn.disabled = true;
            downloadBtn.innerHTML = `
                <span class="flex items-center justify-center">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Generating...
                </span>
            `;
        }
        await this.tick();

        try {
            const canvas = await html2canvas(elementToCapture, {
                backgroundColor: '#ffffff',
                scale: 2,
                useCORS: true,
                logging: false,
                onclone: (clonedDoc) => {
                    const tooltip = clonedDoc.getElementById('tooltip');
                    if (tooltip) {
                        tooltip.style.display = 'none';
                    }

                    // Checkboxes are not agreeing with our styling, so I manually move them down to align with text
                    const criteriaCheckboxes = clonedDoc.querySelectorAll('.w-8.h-8.flex.items-center.justify-center');
                    criteriaCheckboxes.forEach(checkbox => {
                        checkbox.style.position = 'relative';
                        checkbox.style.top = '10px';
                    });

                    
                    //add a margin-bottom to the 3rd and 6th criteria checkbox
                    const thirdCriteriaCheckbox = criteriaCheckboxes[2];
                    thirdCriteriaCheckbox.style.marginBottom = '10px';
                    const sixthCriteriaCheckbox = criteriaCheckboxes[5];
                    sixthCriteriaCheckbox.style.marginBottom = '10px';
                    
                    // Hide underlines from highlighted terms in the generated image.
                    const terms = clonedDoc.querySelectorAll('.headline-term');
                    terms.forEach(term => {
                        term.style.textDecoration = 'none';
                    });

                }
            });
            const imageUrl = canvas.toDataURL('image/png');
            
            this.downloadLink.href = imageUrl;
            this.downloadLink.download = 'reframe-headline-comparison.png';
            this.downloadLink.click();
        } catch (err) {
            console.error('Error generating share image:', err);
            alert('Could not generate share image. Please try again.');
        } finally {
            this.isGenerating = false;
            if (downloadBtn) {
                downloadBtn.disabled = false;
                downloadBtn.innerHTML = 'Download Image';
            }
        }
    }
    
    // Tooltip handling
    handleTooltipShow(event) {
        const target = event.target;
        if (target.classList.contains('headline-term')) {
            const explanation = target.getAttribute('data-explanation');
            if (explanation) {
                this.showTooltip(explanation, event);
            }
        }
    }
    
    handleTooltipHide(event) {
        const target = event.target;
        if (target.classList.contains('headline-term')) {
            this.hideTooltip();
        }
    }
    
    showTooltip(explanation, event) {
        const rect = event.target.getBoundingClientRect();
        const tooltipX = rect.left + (rect.width / 2);
        const tooltipY = rect.top;
        
        this.tooltipText.textContent = explanation;
        this.tooltip.style.left = tooltipX + 'px';
        this.tooltip.style.top = tooltipY + 'px';
        this.tooltip.classList.add('show');
    }
    
    hideTooltip() {
        this.tooltip.classList.remove('show');
    }
    
    // Display error and non-relevant results
    displayError(errorMessage) {
        this.analysisResults.innerHTML = `
            <div class="p-6 border border-black bg-red-50">
                <h3 class="text-2xl font-semibold text-red-700 mb-4">Analysis Error</h3>
                <div class="mb-6 text-lg">
                    <p>An error occurred during analysis: ${this.escapeHtml(errorMessage)}</p>
                    <p class="mt-2">Please try again or contact support if the problem persists.</p>
                </div>
                <button onclick="location.reload()" 
                        class="px-6 py-3 bg-red-700 text-white font-semibold border border-red-700 hover:bg-white hover:text-red-700 transition-all">
                    Try Again
                </button>
            </div>
        `;
        this.showResults();
    }
    
    displayNonRelevantResult() {
        this.analysisResults.innerHTML = `
            <div class="p-6 border border-black">
                <h3 class="text-2xl font-bold text-black mb-4">Not a Traffic Crash Article</h3>
                <div class="mb-6 text-lg">
                    <p>The article you submitted does not appear to be about a traffic crash. This tool is designed specifically to analyze and improve crash reporting headlines.</p>
                </div>
                <button onclick="location.reload()" 
                        class="px-6 py-3 bg-black text-white font-semibold border border-black hover:bg-white hover:text-black transition-all">
                    Try Another Article
                </button>
            </div>
        `;
    }

    
    // Utility functions
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}


// Initialize app when DOM is loaded
let app;
document.addEventListener('DOMContentLoaded', function() {
    app = new ReFrameApp();
}); 