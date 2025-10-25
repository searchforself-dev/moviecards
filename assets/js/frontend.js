(function($) {
    'use strict';
    
    if (typeof ratingsCardData === 'undefined') {
        return;
    }
    
    function lazyLoadPosters() {
        const posters = document.querySelectorAll('.ratings-card-poster img[loading="lazy"]');
        
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                        }
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            posters.forEach(function(img) {
                imageObserver.observe(img);
            });
        }
    }
    
    function performSearch(query, page, container) {
        const resultsContainer = container.find('.ratings-card-search-results');
        
        if (!query || query.length < 2) {
            resultsContainer.html('<div class="search-error">Please enter at least 2 characters.</div>');
            return;
        }
        
        resultsContainer.html('<div class="search-loading">Searching...</div>');
        
        $.ajax({
            url: ratingsCardData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ratings_card_search',
                nonce: ratingsCardData.searchNonce,
                query: query,
                page: page
            },
            success: function(response) {
                if (response.success) {
                    if (page > 1) {
                        resultsContainer.find('.search-results-grid').append($(response.data.html).find('.search-result-item'));
                        resultsContainer.find('.search-results-pagination').replaceWith($(response.data.html).find('.search-results-pagination'));
                    } else {
                        resultsContainer.html(response.data.html);
                    }
                } else {
                    resultsContainer.html('<div class="search-error">' + response.data.message + '</div>');
                }
            },
            error: function() {
                resultsContainer.html('<div class="search-error">Search failed. Please try again.</div>');
            }
        });
    }
    
    function loadMovieRatings(movieId, movieTitle, container) {
        const selectedContainer = container.find('.ratings-card-selected-movie');
        
        selectedContainer.html('<div class="ratings-loading">Loading ratings for ' + movieTitle + '...</div>');
        
        $.ajax({
            url: ratingsCardData.restUrl,
            type: 'GET',
            data: {
                id: movieId,
                type: 'tmdb',
                mediaType: 'movie'
            },
            headers: {
                'X-WP-Nonce': ratingsCardData.nonce
            },
            success: function(data) {
                const html = renderFullRatingsCard(data, movieId);
                selectedContainer.html(html);
                selectedContainer.get(0).scrollIntoView({ behavior: 'smooth', block: 'start' });
            },
            error: function(xhr) {
                let errorMsg = 'Failed to load ratings.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                selectedContainer.html('<div class="ratings-error">' + errorMsg + '</div>');
            }
        });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function escapeAttr(text) {
        return String(text).replace(/&/g, '&amp;')
                           .replace(/'/g, '&#39;')
                           .replace(/"/g, '&quot;')
                           .replace(/</g, '&lt;')
                           .replace(/>/g, '&gt;');
    }
    
    function renderFullRatingsCard(data, movieId) {
        const card = document.createElement('div');
        card.className = 'ratings-card';
        card.setAttribute('data-id', movieId);
        card.setAttribute('data-type', 'tmdb');
        
        if (data.poster_path) {
            const posterDiv = document.createElement('div');
            posterDiv.className = 'ratings-card-poster';
            const img = document.createElement('img');
            img.src = data.poster_path;
            img.alt = data.title || '';
            img.loading = 'lazy';
            posterDiv.appendChild(img);
            card.appendChild(posterDiv);
        }
        
        const content = document.createElement('div');
        content.className = 'ratings-card-content';
        
        const header = document.createElement('div');
        header.className = 'ratings-card-header';
        const title = document.createElement('h3');
        title.className = 'ratings-card-title';
        title.textContent = data.title || '';
        
        if (data.release_date) {
            const year = new Date(data.release_date).getFullYear();
            const yearSpan = document.createElement('span');
            yearSpan.className = 'ratings-card-year';
            yearSpan.textContent = ' (' + year + ')';
            title.appendChild(yearSpan);
        }
        header.appendChild(title);
        content.appendChild(header);
        
        if (data.average) {
            const scoreMain = document.createElement('div');
            scoreMain.className = 'ratings-card-score-main';
            
            const average = document.createElement('div');
            average.className = 'ratings-card-average';
            const scoreValue = document.createElement('span');
            scoreValue.className = 'score-value';
            scoreValue.textContent = data.average.toFixed(1);
            const scoreLabel = document.createElement('span');
            scoreLabel.className = 'score-label';
            scoreLabel.textContent = '/ 10';
            average.appendChild(scoreValue);
            average.appendChild(scoreLabel);
            
            const scoreMeta = document.createElement('div');
            scoreMeta.className = 'ratings-card-score-meta';
            const metaSpan = document.createElement('span');
            metaSpan.textContent = 'Average of ' + Object.keys(data.ratings || {}).length + ' sources';
            scoreMeta.appendChild(metaSpan);
            
            scoreMain.appendChild(average);
            scoreMain.appendChild(scoreMeta);
            content.appendChild(scoreMain);
        }
        
        if (data.ratings && Object.keys(data.ratings).length > 0) {
            const sourcesDiv = document.createElement('div');
            sourcesDiv.className = 'ratings-card-sources';
            
            Object.keys(data.ratings).forEach(function(source) {
                const rating = data.ratings[source];
                const sourceDiv = document.createElement('div');
                sourceDiv.className = 'rating-source ' + source.toLowerCase().replace(/_/g, '-');
                
                const sourceName = document.createElement('span');
                sourceName.className = 'source-name';
                sourceName.textContent = source.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                sourceDiv.appendChild(sourceName);
                
                if (source === 'rottenTomatoes') {
                    if (rating.tomatometer) {
                        const tomScore = document.createElement('span');
                        tomScore.className = 'source-score';
                        tomScore.textContent = 'Tomatometer ' + Math.round(rating.tomatometer) + '%';
                        sourceDiv.appendChild(tomScore);
                    }
                    if (rating.audience) {
                        const audScore = document.createElement('span');
                        audScore.className = 'source-score';
                        audScore.textContent = ' / Audience ' + Math.round(rating.audience) + '%';
                        sourceDiv.appendChild(audScore);
                    }
                } else if (rating.score) {
                    const score = document.createElement('span');
                    score.className = 'source-score';
                    score.textContent = rating.score.toFixed(1);
                    sourceDiv.appendChild(score);
                }
                
                if (rating.votes) {
                    const votes = document.createElement('span');
                    votes.className = 'source-votes';
                    votes.textContent = '(' + rating.votes.toLocaleString() + ')';
                    sourceDiv.appendChild(votes);
                }
                
                if (rating.url) {
                    const link = document.createElement('a');
                    link.href = rating.url;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.className = 'source-link';
                    link.textContent = 'View';
                    sourceDiv.appendChild(link);
                }
                
                sourcesDiv.appendChild(sourceDiv);
            });
            
            content.appendChild(sourcesDiv);
        }
        
        if (data.popularity || data.origin_country) {
            const footer = document.createElement('div');
            footer.className = 'ratings-card-footer';
            const footerParts = [];
            if (data.popularity) footerParts.push('Popularity ' + data.popularity.toFixed(1));
            if (data.origin_country) footerParts.push('Country ' + data.origin_country.join(', '));
            footer.textContent = footerParts.join(' • ');
            content.appendChild(footer);
        }
        
        card.appendChild(content);
        
        return card.outerHTML;
    }
    
    $(document).ready(function() {
        lazyLoadPosters();
        
        $('.ratings-card-search-container').each(function() {
            const container = $(this);
            const searchInput = container.find('.ratings-card-search-input');
            const searchBtn = container.find('.ratings-card-search-btn');
            
            searchBtn.on('click', function(e) {
                e.preventDefault();
                const query = searchInput.val().trim();
                performSearch(query, 1, container);
            });
            
            searchInput.on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    const query = $(this).val().trim();
                    performSearch(query, 1, container);
                }
            });
            
            let searchTimeout;
            searchInput.on('input', function() {
                clearTimeout(searchTimeout);
                const query = $(this).val().trim();
                
                if (query.length >= 3) {
                    searchTimeout = setTimeout(function() {
                        performSearch(query, 1, container);
                    }, 500);
                }
            });

            container.on('click', '.show-ratings-btn', function(e) {
                e.preventDefault();
                const movieId = $(this).data('movie-id');
                const movieTitle = $(this).data('movie-title');
                loadMovieRatings(movieId, movieTitle, container);
            });

            container.on('click', '.load-more-results', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                const query = $(this).data('query');
                performSearch(query, page, container);
            });
        });
    });
    
})(jQuery);
