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
  const logoScale = useSharedValue(0.9);
  const logoOpacity = useSharedValue(0);
  const logoLift = useSharedValue(8);
  const logoRotate = useSharedValue(-10);
  const wordOpacity = useSharedValue(0);
  const wordTranslate = useSharedValue(12);
  const taglineOpacity = useSharedValue(0);

  useEffect(() => {
    logoOpacity.value = withTiming(1, { duration: 420, easing: Easing.out(Easing.quad) });
    logoScale.value = withSequence(
      withTiming(1.03, { duration: 360, easing: Easing.out(Easing.cubic) }),
      withTiming(1, { duration: 260, easing: Easing.inOut(Easing.quad) }),
    );
    logoLift.value = withTiming(0, { duration: 520, easing: Easing.out(Easing.cubic) });
    logoRotate.value = withSequence(
      withTiming(6, { duration: 360, easing: Easing.out(Easing.cubic) }),
      withDelay(
        40,
        withSequence(
          withTiming(-2, { duration: 220, easing: Easing.inOut(Easing.quad) }),
          withTiming(0, { duration: 180, easing: Easing.out(Easing.quad) }),
        ),
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
    transform: [
      { translateY: logoLift.value },
      { rotate: `${logoRotate.value}deg` },
      { scale: logoScale.value },
    ],
  }));
  const wordStyle = useAnimatedStyle(() => ({
    opacity: wordOpacity.value,
    transform: [{ translateY: wordTranslate.value }],
  }));
  const taglineStyle = useAnimatedStyle(() => ({ opacity: taglineOpacity.value }));

  return (
    <View className="flex-1 items-center justify-center bg-[#f8faf6]">
      <View className="items-center">
        <View className="items-center justify-center" style={{ height: 88, width: 88 }}>
          <Animated.View style={logoStyle}>
            <Svg width={72} height={72} viewBox="0 0 32 32" fill="none">
              <Circle cx="16" cy="16" r="12" stroke="#ea580c" strokeWidth="1.5" strokeDasharray="4 2" opacity="0.75" />
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
