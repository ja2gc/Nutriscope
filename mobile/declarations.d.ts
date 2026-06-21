// Allow importing CSS files (used by NativeWind global.css)
declare module '*.css' {
  const content: string;
  export default content;
}
