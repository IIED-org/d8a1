var gulp = require('gulp');
var browserSync = require('browser-sync').create();
var sass = require('gulp-sass');
var concat = require("gulp-concat");
var cleanCSS = require("gulp-clean-css");
var uglify = require("gulp-uglify");
var sourcemaps = require('gulp-sourcemaps');

// Compile sass into CSS & auto-inject into browsers
gulp.task('sass', function() {
    return gulp.src(['node_modules/bootstrap/scss/bootstrap.scss', 'scss/style.scss'])
        .pipe(sourcemaps.init())
        .pipe(sass().on('error', sass.logError))
        .pipe(sass({ outputStyle: 'compressed' }))      // nested | compressed etc.
        //.pipe(sourcemaps.write())                    // Comment out in prod mode
        .pipe(cleanCSS({compatibility: 'ie10'}))      // Remove comment in prod mode
        .pipe(gulp.dest("css"))
        .pipe(browserSync.stream());
});

// Move the javascript files into our js folder
gulp.task('js', function() {
    return gulp.src(['node_modules/bootstrap/dist/js/bootstrap.min.js', 'node_modules/jquery/dist/jquery.min.js', 'node_modules/popper.js/dist/umd/popper.min.js'])
        .pipe(gulp.dest("js"))
        .pipe(browserSync.stream());
});

// Static Server + watching scss/html files
gulp.task('serve', ['sass'], function() {

    browserSync.init({
        proxy: "http://pnp.dev.dd:8083",
    });

    gulp.watch(['node_modules/bootstrap/scss/bootstrap.scss', 'scss/*.scss', 'scss/components/*.scss'], ['sass']);
    //    gulp.watch("src/*.html").on('change', browserSync.reload);
});

gulp.task('default', ['js', 'serve']);
