plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
    id("org.jetbrains.kotlin.plugin.compose")
}

val nativephpMobileRoot = providers.gradleProperty("nativephpMobileRoot").orNull
    ?.let(::file)
    ?: listOf(
        rootProject.file("../vendor/nativephp/mobile"),
        rootProject.file("../../../../vendor/nativephp/mobile"),
    ).firstOrNull { it.isDirectory }
    ?: error("NativePHP Mobile source not found. Run composer install or pass -PnativephpMobileRoot=/path/to/vendor/nativephp/mobile")
val nativeUINodeSource = nativephpMobileRoot.resolve(
    "resources/androidstudio/app/src/main/java/com/nativephp/mobile/ui/nativerender/NativeUINode.kt",
)
check(nativeUINodeSource.isFile) { "NativePHP Mobile NativeUINode.kt not found under $nativephpMobileRoot" }

val syncNativePHPContract by tasks.registering(Copy::class) {
    from(nativeUINodeSource)
    into(layout.buildDirectory.dir("generated/nativephp-contract/com/nativephp/mobile/ui/nativerender"))
}

android {
    namespace = "com.donmanueldev.plugins.nativephp_charts.harness"
    compileSdk = 35

    defaultConfig {
        applicationId = "com.donmanueldev.plugins.nativephp_charts.harness"
        minSdk = 26
        targetSdk = 35
        versionCode = 1
        versionName = "1.0"
        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
    }

    sourceSets.getByName("main") {
        java.srcDir("../../resources/android/src")
        java.srcDir(layout.buildDirectory.dir("generated/nativephp-contract"))
    }
    sourceSets.getByName("test") {
        resources.srcDir("../../fixtures/contracts")
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    kotlinOptions { jvmTarget = "17" }
    buildFeatures { compose = true }
    buildTypes {
        create("profile") {
            initWith(getByName("release"))
            signingConfig = signingConfigs.getByName("debug")
            isDebuggable = false
            isMinifyEnabled = false
            matchingFallbacks += listOf("release")
        }
    }
    testOptions { unitTests.isReturnDefaultValues = true }
}

tasks.named("preBuild").configure { dependsOn(syncNativePHPContract) }

dependencies {
    val composeBom = platform("androidx.compose:compose-bom:2025.12.00")
    implementation(composeBom)
    implementation("androidx.compose.ui:ui")
    implementation("androidx.compose.foundation:foundation")
    implementation("androidx.compose.material3:material3")
    implementation("androidx.activity:activity-compose:1.10.1")
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.8.1")

    testImplementation("junit:junit:4.13.2")
    testImplementation("org.json:json:20250517")
    testImplementation("org.jetbrains.kotlinx:kotlinx-coroutines-test:1.8.1")

    androidTestImplementation(composeBom)
    androidTestImplementation("androidx.compose.ui:ui-test-junit4")
    androidTestImplementation("androidx.test.ext:junit:1.3.0")
    androidTestImplementation("androidx.test:runner:1.6.2")
    debugImplementation("androidx.compose.ui:ui-test-manifest")
}
