'use strict';

const gulp          = require('gulp');
const source        = require('vinyl-source-stream');
const buffer        = require('vinyl-buffer');
const gutil         = require('gulp-util');
const browserify    = require('browserify');
const uglify        = require('gulp-uglify');
const sourcemaps    = require('gulp-sourcemaps');
const rename        = require('gulp-rename');
const sass          = require('gulp-sass');
const cleanCSS      = require('gulp-clean-css');
const postcss       = require('gulp-postcss');
const autoprefixer  = require('autoprefixer');
const concat        = require('gulp-concat');

const production    = !!gutil.env.production;


// Build bundle.js
gulp.task('scripts', function () {
    return browserify({
            entries: './app/js/main.js',
            debug: !production
        })
        .ignore('electron')
        .transform('babelify', {presets: ['react', 'es2015']})
        .bundle()
        .pipe(source('./app/js/main.js'))
        .pipe(buffer())
        .pipe(production ? uglify() : gutil.noop())
        .pipe(rename('bundle.js'))
        .on('error', gutil.log)
        .pipe(production ? sourcemaps.write() : gutil.noop())
        .pipe(gulp.dest('./app/bundles'));
});


// Build bundle.css
gulp.task('styles', function () {
    return gulp.src('app/css/main.scss')
        .pipe(production ? sourcemaps.init() : gutil.noop())
        .pipe(sass())
        .pipe(postcss([ autoprefixer({ browsers: ['> 5%'] }) ]))
        .pipe(production ? sourcemaps.write() : gutil.noop())
        .pipe(concat('bundle.css'))
        .pipe(production ? cleanCSS() : gutil.noop())
        .pipe(gulp.dest('./app/bundles'));
});

// Build by default
gulp.task('default', ['scripts', 'styles']);

// Watch task
gulp.task('watch', function() {
    gulp.watch(['./app/js/**/*.js'], ['scripts']);
    gulp.watch('./app/css/**/*.scss', ['styles']);
});

// Build task
gulp.task('build', ['scripts', 'styles']);

// Build and watch task
gulp.task('build:watch', ['build', 'watch']);
