import { useEffect } from 'react';
import { Text, View } from 'react-native';
import Animated, {
  Easing,
  useAnimatedStyle,
  useSharedValue,
  withDelay,
  withSequence,
  withTiming,
} from 'react-native-reanimated';
import Svg, { Circle, Line, Path } from 'react-native-svg';

/**
 * Branded launch animation shown while the app bootstraps (token check). The
 * NutriScope leaf + diagnostic-scope crosshair draws in, the wordmark fades up,
 * and a soft tagline settles below — same mark as the website/login so the app
 * reads as one organization from the very first frame.
 */
export default function AnimatedSplash({ onDone }: { onDone?: () => void }) {
  const logoScale = useSharedValue(0.6);
  const logoOpacity = useSharedValue(0);
  const ringScale = useSharedValue(0.4);
  const ringOpacity = useSharedValue(0);
  const wordOpacity = useSharedValue(0);
  const wordTranslate = useSharedValue(12);
  const taglineOpacity = useSharedValue(0);

  useEffect(() => {
    ringOpacity.value = withTiming(0.75, { duration: 600, easing: Easing.out(Easing.quad) });
    ringScale.value = withTiming(1, { duration: 800, easing: Easing.out(Easing.back(1.4)) });

    logoOpacity.value = withDelay(150, withTiming(1, { duration: 500 }));
    logoScale.value = withDelay(
      150,
      withSequence(
        withTiming(1.08, { duration: 450, easing: Easing.out(Easing.cubic) }),
        withTiming(1, { duration: 250, easing: Easing.inOut(Easing.quad) }),
      ),
    );

    wordOpacity.value = withDelay(550, withTiming(1, { duration: 450 }));
    wordTranslate.value = withDelay(550, withTiming(0, { duration: 450, easing: Easing.out(Easing.cubic) }));

    taglineOpacity.value = withDelay(850, withTiming(1, { duration: 450 }));

    const timer = setTimeout(() => onDone?.(), 1900);
    return () => clearTimeout(timer);
  }, []);

  const logoStyle = useAnimatedStyle(() => ({
    opacity: logoOpacity.value,
    transform: [{ scale: logoScale.value }],
  }));
  const ringStyle = useAnimatedStyle(() => ({
    opacity: ringOpacity.value,
    transform: [{ scale: ringScale.value }],
  }));
  const wordStyle = useAnimatedStyle(() => ({
    opacity: wordOpacity.value,
    transform: [{ translateY: wordTranslate.value }],
  }));
  const taglineStyle = useAnimatedStyle(() => ({ opacity: taglineOpacity.value }));

  return (
    <View className="flex-1 items-center justify-center bg-white">
      <View className="items-center">
        <View className="items-center justify-center" style={{ height: 120, width: 120 }}>
          {/* Pulsing outer scope ring */}
          <Animated.View style={[{ position: 'absolute' }, ringStyle]}>
            <Svg width={120} height={120} viewBox="0 0 32 32" fill="none">
              <Circle cx="16" cy="16" r="13" stroke="#ea580c" strokeWidth="0.8" strokeDasharray="4 2" opacity="0.5" />
            </Svg>
          </Animated.View>

          {/* Core mark */}
          <Animated.View style={logoStyle}>
            <Svg width={96} height={96} viewBox="0 0 32 32" fill="none">
              <Circle cx="16" cy="16" r="12" stroke="#ea580c" strokeWidth="1.5" strokeDasharray="4 2" opacity="0.75" />
              <Circle cx="16" cy="16" r="6" stroke="#ea580c" strokeWidth="1" opacity="0.4" />
              <Line x1="16" y1="2" x2="16" y2="6" stroke="#ea580c" strokeWidth="1.5" />
              <Line x1="16" y1="26" x2="16" y2="30" stroke="#ea580c" strokeWidth="1.5" />
              <Line x1="2" y1="16" x2="6" y2="16" stroke="#ea580c" strokeWidth="1.5" />
              <Line x1="26" y1="16" x2="30" y2="16" stroke="#ea580c" strokeWidth="1.5" />
              <Path d="M16 8C16 8 10 13 10 18C10 21.3137 12.6863 24 16 24C19.3137 24 22 21.3137 22 18C22 13 16 8 16 8Z" fill="#059669" />
              <Path d="M16 8C16 13 18.5 17 21 19.5" stroke="#d1fae5" strokeWidth="1" strokeLinecap="round" opacity="0.9" />
              <Path d="M16 24V14" stroke="#10b981" strokeWidth="1.2" strokeLinecap="round" />
            </Svg>
          </Animated.View>
        </View>

        <Animated.View style={[wordStyle, { marginTop: 16 }]} className="flex-row items-baseline">
          <Text className="text-3xl font-extrabold tracking-tight text-emerald-600">Nutri</Text>
          <Text className="text-3xl font-extrabold tracking-tight text-orange-600">Scope</Text>
        </Animated.View>

        <Animated.Text
          style={taglineStyle}
          className="text-[10px] font-semibold text-zinc-400 uppercase tracking-[3px] mt-2"
        >
          Food Service Operations
        </Animated.Text>
      </View>
    </View>
  );
}
