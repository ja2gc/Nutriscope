export async function DELETE() {
  return Response.json({ message: "Reports cannot be deleted." }, { status: 405 });
}
