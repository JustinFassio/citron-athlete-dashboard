jQuery(document).ready(function($) {
    const { ajax_url: ajaxUrl, nonce, exerciseTests } = athleteDashboard;
    let progressChart;
    let comprehensiveBodyCompositionChart;
    let currentUnit = 'kg';
    const exerciseCharts = {};

    function initExerciseTabs() {
        $("#exercise-tabs").tabs({
            create: function(event, ui) {
                const firstExerciseKey = Object.keys(exerciseTests)[0];
                initExerciseChart(firstExerciseKey);
                updateExerciseChart(firstExerciseKey);
            },
            activate: function(event, ui) {
                const exerciseKey = ui.newPanel.attr('id');
                if (!exerciseCharts[exerciseKey]) {
                    initExerciseChart(exerciseKey);
                }
                updateExerciseChart(exerciseKey);
            }
        });
    }

    function toggleSection($btn) {
        const $section = $btn.closest('.dashboard-section');
        const $content = $section.find('.section-content');
        const isExpanding = !$btn.hasClass('active');

        $btn.toggleClass('active').text(isExpanding ? '-' : '+');
        $section.toggleClass('active');

        $content.slideToggle({
            duration: 300,
            easing: 'easeOutQuad',
            complete: function() {
                $btn.attr('aria-expanded', isExpanding);
                $content.attr('aria-hidden', !isExpanding);
                localStorage.setItem(`section-${$section.attr('id')}`, isExpanding);
                
                if (isExpanding) {
                    if ($section.attr('id') === 'exercise-progress') {
                        $("#exercise-tabs").tabs("refresh");
                        const activeTabId = $("#exercise-tabs").tabs("option", "active");
                        const activeExerciseKey = $("#exercise-tabs ul>li").eq(activeTabId).find('a').attr('href').substring(1);
                        if (exerciseCharts[activeExerciseKey]) {
                            exerciseCharts[activeExerciseKey].update();
                        }
                    } else if ($section.attr('id') === 'comprehensive-body-composition') {
                        if (comprehensiveBodyCompositionChart) {
                            comprehensiveBodyCompositionChart.update();
                        }
                    }
                }
            }
        });
    }

    function restoreSectionStates() {
        $('.dashboard-section').each(function() {
            const $section = $(this);
            const $content = $section.find('.section-content');
            const $toggleBtn = $section.find('.toggle-btn');
            const isOpen = localStorage.getItem(`section-${$section.attr('id')}`) === 'true';

            $toggleBtn.text(isOpen ? '-' : '+')
                .toggleClass('active', isOpen)
                .attr('aria-expanded', isOpen);
            $section.toggleClass('active', isOpen);
            $content.toggle(isOpen).attr('aria-hidden', !isOpen);
        });
    }

    function initProfilePictureUpload() {
        $('#change-avatar').on('click', function(e) {
            e.preventDefault();
            var fileInput = $('<input type="file" accept="image/*" style="display:none">');
            fileInput.on('change', function(e) {
                var file = e.target.files[0];
                var formData = new FormData();
                formData.append('action', 'update_profile_picture');
                formData.append('nonce', nonce);
                formData.append('profile_picture', file);

                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $('.profile-picture img').attr('src', response.data.url);
                            alert('Profile picture updated successfully!');
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred. Please try again.');
                    }
                });
            });
            fileInput.trigger('click');
        });
    }

    function initProfileEdit() {
        $('#edit-profile').on('click', function(e) {
            e.preventDefault();
            var $form = $('#account-details-form');
            $form.find('.profile-info').hide();
            $form.find('.edit-profile-fields').show();
            $(this).hide();
            $('#save-profile').show();
        });

        $('#save-profile').on('click', function(e) {
            e.preventDefault();
            var formData = new FormData($('#account-details-form')[0]);
            formData.append('action', 'update_profile');
            formData.append('nonce', nonce);

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert('Profile updated successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        });
    }

    function initProgressChart() {
        const ctx = document.getElementById('progressChart');
        if (!ctx) return;

        progressChart = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [{
                    label: 'Body Weight Progress',
                    data: [],
                    fill: false,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            displayFormats: {
                                day: 'MMM d, yyyy'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'Weight (kg)'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const { y } = context.parsed;
                                return y !== null ? `${context.dataset.label || ''}: ${y.toFixed(2)} ${currentUnit}` : '';
                            }
                        }
                    }
                }
            }
        });
    }

    function initComprehensiveBodyCompositionChart() {
        const ctx = document.getElementById('comprehensiveBodyCompositionChart');
        if (!ctx) return;

        comprehensiveBodyCompositionChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Weight (kg)',
                        data: [],
                        borderColor: 'rgb(75, 192, 192)',
                        yAxisID: 'y-axis-1'
                    },
                    {
                        label: 'Body Fat (%)',
                        data: [],
                        borderColor: 'rgb(255, 99, 132)',
                        yAxisID: 'y-axis-2'
                    },
                    {
                        label: 'Muscle Mass (kg)',
                        data: [],
                        borderColor: 'rgb(54, 162, 235)',
                        yAxisID: 'y-axis-1'
                    },
                    {
                        label: 'BMI',
                        data: [],
                        borderColor: 'rgb(255, 206, 86)',
                        yAxisID: 'y-axis-2'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                stacked: false,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            displayFormats: {
                                day: 'MMM d, yyyy'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    'y-axis-1': {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Weight / Muscle Mass (kg)'
                        }
                    },
                    'y-axis-2': {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Body Fat (%) / BMI'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += context.parsed.y.toFixed(2);
                                }
                                return label;
                            }
                        }
                    },
                    zoom: {
                        zoom: {
                            wheel: {
                                enabled: true,
                            },
                            pinch: {
                                enabled: true
                            },
                            mode: 'x',
                        },
                        pan: {
                            enabled: true,
                            mode: 'x',
                        },
                    }
                }
            }
        });
    }

    function convertWeight(weight, fromUnit, toUnit) {
        if (fromUnit === toUnit) return weight;
        return fromUnit === 'kg' ? weight * 2.20462 : weight / 2.20462;
    }

    function updateProgressChart() {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'get_user_progress', nonce },
            success: function(response) {
                if (response.success && response.data && progressChart) {
                    const chartData = response.data.datasets[0].data.map(item => ({
                        x: item.x,
                        y: convertWeight(item.y, 'kg', currentUnit)
                    }));
                    progressChart.data.datasets[0].data = chartData;
                    progressChart.options.scales.y.title.text = `Weight (${currentUnit})`;
                    progressChart.update();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error updating progress chart:', error);
            }
        });
    }

    function updateComprehensiveBodyCompositionChart(startDate = null, endDate = null) {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_comprehensive_body_composition_progress',
                nonce: nonce,
                start_date: startDate,
                end_date: endDate
            },
            success: function(response) {
                if (response.success && comprehensiveBodyCompositionChart) {
                    const data = response.data;
                    comprehensiveBodyCompositionChart.data.labels = data.labels;
                    comprehensiveBodyCompositionChart.data.datasets.forEach((dataset, index) => {
                        dataset.data = data.datasets[index].data;
                    });
                    comprehensiveBodyCompositionChart.update();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error updating comprehensive body composition chart:', error);
            }
        });
    }

    function initProgressForm() {
        $('#progress-form').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serializeArray();
            const weightUnit = $('#weight_unit').val();
            const weight = parseFloat($('#weight').val());

            const weightInKg = weightUnit === 'lbs' ? convertWeight(weight, 'lbs', 'kg') : weight;

            formData.push(
                { name: 'action', value: 'handle_progress_submission' },
                { name: 'nonce', value: nonce },
                { name: 'weight', value: weightInKg.toFixed(2) },
                { name: 'weight_unit', value: 'kg' }
            );

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        updateProgressChart();
                        $('#progress-form')[0].reset();
                        
                        $.ajax({
                            url: ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'get_most_recent_weight',
                                nonce: nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    $('#body_weight').val(response.data);
                                }
                            }
                        });
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        });

        $('#weight_unit').on('change', function() {
            currentUnit = $(this).val();
            updateProgressChart();
        });
    }

    function initComprehensiveBodyCompositionForm() {
        $('#comprehensive-body-composition-form').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serializeArray();
            formData.push(
                { name: 'action', value: 'store_comprehensive_body_composition_progress' },
                { name: 'nonce', value: nonce }
            );

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        updateComprehensiveBodyCompositionChart();
                        $('#comprehensive-body-composition-form')[0].reset();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Error submitting comprehensive body composition data:', textStatus, errorThrown);
                    alert('An error occurred. Please try again.');
                }
            });
        });
    }

    function initExerciseChart(exerciseKey) {
        const ctx = document.getElementById(`${exerciseKey}-chart`);
        if (!ctx) return;

        // If a chart already exists for this exercise, destroy it
        if (exerciseCharts[exerciseKey]) {
            exerciseCharts[exerciseKey].destroy();
        }

        const test = exerciseTests[exerciseKey];
        exerciseCharts[exerciseKey] = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [{
                    label: test.label,
                    data: [],
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            displayFormats: {
                                day: 'MMM d, yyyy'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: `${test.label} (${test.unit})`
                        }
                    }
                }
            }
        });
    }

    function updateExerciseChart(exerciseKey) {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_exercise_progress',
                nonce: nonce,
                exercise_key: exerciseKey
            },
            success: function(response) {
                if (response.success && exerciseCharts[exerciseKey]) {
                    exerciseCharts[exerciseKey].data.datasets[0].data = response.data.map(item => ({
                        x: new Date(item.date),
                        y: parseFloat(item.value)
                    }));
                    exerciseCharts[exerciseKey].update();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error updating exercise chart:', error);
            }
        });
    }

    function initExerciseProgressForm() {
        $('.exercise-progress-form').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serializeArray();
            const exerciseKey = formData.find(item => item.name === 'exercise_key').value;

            const ajaxData = {
                action: 'handle_exercise_progress_submission',
                nonce: nonce,
                ...Object.fromEntries(formData)
            };

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    if (response.success) {
                        alert('Progress added successfully!');
                        updateExerciseChart(exerciseKey);
                        e.target.reset();
                        
                        // Update the corresponding user profile field
                        const latestValue = formData.find(item => item.name === 'value').value;
                        $(`#${exerciseKey}`).val(latestValue);
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX request failed:', textStatus, errorThrown);
                    alert('An error occurred while submitting the form. Please try again.');
                }
            });
        });
    }

    function initComprehensiveDateRangeFilter() {
        $('#comprehensive-date-range-filter').on('submit', function(e) {
            e.preventDefault();
            const startDate = $('#comprehensive-start-date').val();
            const endDate = $('#comprehensive-end-date').val();
            updateComprehensiveBodyCompositionChart(startDate, endDate);
        });
    }

    function init() {
        initExerciseTabs();
        restoreSectionStates();
        initProfilePictureUpload();
        initProfileEdit();
        initProgressChart();
        initProgressForm();
        initExerciseProgressForm();
        initComprehensiveBodyCompositionChart();
        initComprehensiveBodyCompositionForm();
        initComprehensiveDateRangeFilter();

        $('.toggle-btn').on('click', function(e) {
            e.preventDefault();
            toggleSection($(this));
        });

        // Initialize all exercise charts
        Object.keys(exerciseTests).forEach(key => {
            initExerciseChart(key);
            updateExerciseChart(key);
        });

        updateProgressChart();
        updateComprehensiveBodyCompositionChart();
        setInterval(updateProgressChart, 300000); // Refresh progress chart every 5 minutes
    }

    // Run initialization
    init();

    // Resize Event Listener
    window.addEventListener('resize', function() {
        if (progressChart) {
            progressChart.resize();
        }
        if (comprehensiveBodyCompositionChart) {
            comprehensiveBodyCompositionChart.resize();
        }
        Object.values(exerciseCharts).forEach(chart => {
            if (chart) {
                chart.resize();
            }
        });
    });
});